<?php

namespace KLXM\ConsentKit\Backend;

use KLXM\ConsentKit\Cache;
use KLXM\ConsentKit\I18n;
use KLXM\ConsentKit\PresetRepository;
use KLXM\ConsentKit\Repository;
use rex;
use rex_addon;
use rex_article;
use rex_csrf_token;
use rex_fragment;
use rex_i18n;
use rex_request;
use rex_response;
use rex_sql;
use rex_url;
use rex_view;
use rex_yrewrite;

/** Seite "Dienste": Uebersicht, Vorlagen-Auswahl, Dienst- und Gruppenformular. */
final class ServiceController
{
    private rex_csrf_token $csrf;
    private string $lang;

    public function __construct()
    {
        $this->csrf = rex_csrf_token::factory('consent_kit');
        $this->lang = rex_i18n::getLanguage();
        Repository::syncYrewriteDomains();
    }

    public function handle(): string
    {
        $func = rex_request::request('func', 'string', '');
        $message = '';

        if ('post' === rex_request::requestMethod()) {
            if (!$this->csrf->isValid()) {
                $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
            } else {
                $message = $this->post($func);
            }
        }

        return match ($func) {
            'add' => $message . $this->presetPicker(),
            'edit', 'save' => $message . $this->serviceForm(rex_request::request('id', 'int', 0)),
            'group', 'group_save' => $message . $this->groupForm(rex_request::request('id', 'int', 0)),
            default => $this->flash() . $message . $this->overview(),
        };
    }

    /** Fuehrt eine Aktion aus; bei Erfolg endet der Request mit einem Redirect. */
    private function post(string $func): string
    {
        $id = rex_request::post('id', 'int', 0);
        switch ($func) {
            case 'status':
                Repository::setServiceStatus($id, 1 === rex_request::post('status', 'int', 0));
                $this->redirect('status_saved');
            case 'domain':
                if (!Repository::toggleServiceDomain($id, rex_request::post('domain', 'int', 0))) {
                    if ('consent-kit' === rex_request::server('HTTP_X_REQUESTED_WITH', 'string', '')) {
                        rex_response::cleanOutputBuffers();
                        rex_response::sendContent(rex_view::warning(rex_i18n::msg('consent_kit_domain_last')) . $this->overview());
                        exit;
                    }
                    return rex_view::warning(rex_i18n::msg('consent_kit_domain_last'));
                }
                $this->redirect('');
            case 'move':
                Repository::move(rex_request::post('type', 'string', 'service'), $id, rex_request::post('direction', 'int', 1));
                $this->redirect('');
            case 'delete':
                Repository::deleteService($id);
                $this->redirect('service_deleted');
            case 'group_delete':
                if (Repository::deleteGroup($id)) {
                    $this->redirect('group_deleted');
                }
                return rex_view::error(rex_i18n::msg('consent_kit_group_not_empty'));
            case 'preset':
                return $this->addPreset(rex_request::post('preset', 'string', ''));
            case 'reset':
                return $this->resetToPreset($id);
            case 'save':
                return $this->saveService($id);
            case 'group_save':
                return $this->saveGroup($id);
        }
        return '';
    }

    private function redirect(string $flash, string $func = '', int $id = 0): never
    {
        // Schalter der Uebersicht kommen per fetch und brauchen nur die neue Tabelle.
        if ('' === $func && 'consent-kit' === rex_request::server('HTTP_X_REQUESTED_WITH', 'string', '')) {
            rex_response::cleanOutputBuffers();
            rex_response::sendContent($this->overview());
            exit;
        }
        $params = array_filter(['flash' => $flash, 'func' => $func, 'id' => $id]);
        rex_response::sendRedirect(rex_url::backendPage('consent_kit/services', $params, false));
    }

    private function flash(): string
    {
        $flash = rex_request::get('flash', 'string', '');
        $allowed = ['status_saved', 'service_deleted', 'group_deleted', 'service_saved', 'group_saved', 'preset_added', 'preset_reset'];
        return in_array($flash, $allowed, true) ? rex_view::success(rex_i18n::msg('consent_kit_flash_' . $flash)) : '';
    }

    private function addPreset(string $key): string
    {
        $converted = PresetRepository::toService($key);
        if (null === $converted) {
            return rex_view::error(rex_i18n::msg('consent_kit_preset_unknown'));
        }
        [$data, $items] = $converted;
        if (Repository::serviceKeyExists($key)) {
            return rex_view::error(rex_i18n::msg('consent_kit_key_exists', $key));
        }
        if (0 === $data['group_id']) {
            $data['group_id'] = Repository::groups()[0]['id'] ?? 0;
        }
        $id = Repository::saveService(0, $data, $items);
        // Braucht die Vorlage Angaben (z. B. eine Mess-ID), direkt ins Formular.
        $this->redirect('preset_added', [] !== PresetRepository::params($key) ? 'edit' : '', $id);
    }

    private function resetToPreset(int $id): string
    {
        $service = Repository::service($id);
        $converted = null === $service ? null : PresetRepository::toService($service['preset']);
        if (null === $service || null === $converted) {
            return rex_view::error(rex_i18n::msg('consent_kit_preset_unknown'));
        }
        [$data, $items] = $converted;
        $data['key'] = $service['key'];
        $data['group_id'] = $service['group_id'];
        $data['status'] = $service['status'];
        $data['params'] = $service['params'];
        $data['domain_ids'] = $service['domain_ids'];
        Repository::saveService($id, $data, $items);
        $this->redirect('preset_reset', 'edit', $id);
    }

    private function saveService(int $id): string
    {
        $data = rex_request::post('service', 'array', []);
        $data['key'] = strtolower(trim((string) ($data['key'] ?? '')));
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['embed_hosts'] = preg_split('~[\s,]+~', (string) ($data['embed_hosts'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $data['description'] = Form::stringMap($data['description'] ?? []);
        $data['params'] = Form::stringMap($data['params'] ?? []);

        $errors = [];
        if ('selected' !== ($data['domain_mode'] ?? 'all')) {
            $data['domain_ids'] = [];
        } elseif ([] === array_filter((array) ($data['domain_ids'] ?? []))) {
            $errors[] = rex_i18n::msg('consent_kit_error_domains');
        }
        if (1 !== preg_match('~^[a-z0-9_]{2,64}$~', $data['key'])) {
            $errors[] = rex_i18n::msg('consent_kit_error_key');
        } elseif (Repository::serviceKeyExists($data['key'], $id)) {
            $errors[] = rex_i18n::msg('consent_kit_key_exists', $data['key']);
        }
        if ('' === $data['name']) {
            $errors[] = rex_i18n::msg('consent_kit_error_name');
        }
        if (null === Repository::group((int) ($data['group_id'] ?? 0))) {
            $errors[] = rex_i18n::msg('consent_kit_error_group');
        }
        $privacyUrl = trim((string) ($data['privacy_url'] ?? ''));
        if ('' !== $privacyUrl && 1 !== preg_match('~^https?://~i', $privacyUrl)) {
            $errors[] = rex_i18n::msg('consent_kit_error_url');
        }
        foreach (PresetRepository::params((string) ($data['preset'] ?? '')) as $param) {
            $value = trim($data['params'][$param['key']] ?? '');
            if ('' !== $value && '' !== $param['pattern'] && 1 !== @preg_match('~' . str_replace('~', '\~', $param['pattern']) . '~', $value)) {
                $errors[] = rex_i18n::msg('consent_kit_error_param', I18n::pick($param['label'], $this->lang), $param['placeholder']);
            }
        }
        if ([] !== $errors) {
            return rex_view::error(implode('<br>', $errors));
        }

        $items = [];
        foreach (rex_request::post('items', 'array', []) as $item) {
            if (is_array($item)) {
                $item['purpose'] = Form::stringMap($item['purpose'] ?? []);
                $items[] = $item;
            }
        }
        $variants = array_values(array_filter(rex_request::post('variants', 'array', []), 'is_array'));
        $id = Repository::saveService($id, $data, $items, $variants);
        $this->redirect('service_saved', rex_request::post('save_and_close', 'bool', false) ? '' : 'edit', $id);
    }

    private function saveGroup(int $id): string
    {
        $data = rex_request::post('group', 'array', []);
        $data['key'] = strtolower(trim((string) ($data['key'] ?? '')));
        $data['name'] = Form::stringMap($data['name'] ?? []);
        $data['description'] = Form::stringMap($data['description'] ?? []);
        if (1 !== preg_match('~^[a-z0-9_]{2,64}$~', $data['key'])) {
            return rex_view::error(rex_i18n::msg('consent_kit_error_key'));
        }
        if ([] === array_filter($data['name'])) {
            return rex_view::error(rex_i18n::msg('consent_kit_error_name'));
        }
        $existing = rex_sql::factory()->getArray('SELECT id FROM ' . rex::getTable('consent_kit_group') . ' WHERE `key` = ? AND id <> ?', [$data['key'], $id]);
        if ([] !== $existing) {
            return rex_view::error(rex_i18n::msg('consent_kit_key_exists', $data['key']));
        }
        Repository::saveGroup($id, $data);
        $this->redirect('group_saved');
    }

    /* ------------------------------------------------------------ Uebersicht */

    private function overview(): string
    {
        $groups = Repository::groups();
        $services = Repository::services();
        $byGroup = [];
        foreach ($services as $service) {
            $byGroup[$service['group_id']][] = $service;
        }
        $domains = [];
        foreach (Repository::domains() as $domain) {
            if ('*' !== $domain['host']) {
                $domains[$domain['id']] = $domain['host'];
            }
        }
        // Mit nur einer Domain gibt es nichts zuzuordnen.
        if (count($domains) < 2) {
            $domains = [];
        }
        $columns = 4 + count($domains);

        $head = '<tr><th class="ck-col-status" scope="col">' . rex_i18n::msg('consent_kit_status') . '</th><th scope="col">' . rex_i18n::msg('consent_kit_service') . '</th>';
        foreach ($domains as $host) {
            $head .= '<th class="ck-col-domain" scope="col"><span title="' . rex_escape($host) . '">' . rex_escape($host) . '</span></th>';
        }
        $head .= '<th class="ck-col-entries" scope="col">' . rex_i18n::msg('consent_kit_entries') . '</th><th class="ck-col-actions" scope="col"><span class="sr-only">' . rex_i18n::msg('consent_kit_actions') . '</span></th></tr>';

        $body = '';
        foreach ($groups as $groupIndex => $group) {
            $list = $byGroup[$group['id']] ?? [];
            $name = I18n::pick($group['name'], $this->lang);
            $actions = $this->moveButtons('group', $group['id'], $groupIndex, count($groups), $name)
                . '<a class="btn btn-default btn-xs" href="' . rex_url::backendPage('consent_kit/services', ['func' => 'group', 'id' => $group['id']]) . '" title="' . rex_i18n::msg('consent_kit_edit') . '"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_edit') . ': ' . rex_escape($name) . '</span></a>';
            if ([] === $list) {
                $actions .= $this->postButton('group_delete', ['id' => $group['id']], '<i class="rex-icon fa-trash-o" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_delete') . ': ' . rex_escape($name) . '</span>', 'btn-default btn-xs', rex_i18n::msg('consent_kit_confirm_delete', $name));
            }
            $body .= '<tr class="ck-group-row"><th colspan="' . ($columns - 1) . '" scope="colgroup"><span class="ck-group-name">' . rex_escape($name) . '</span> <code>' . rex_escape($group['key']) . '</code>'
                . ($group['required'] ? ' <span class="label label-default">' . rex_i18n::msg('consent_kit_required') . '</span>' : '')
                . ' <span class="ck-group-desc">' . rex_escape(I18n::pick($group['description'], $this->lang)) . '</span></th>'
                . '<td class="ck-col-actions"><div class="ck-actions">' . $actions . '</div></td></tr>';
            foreach ($list as $index => $service) {
                $body .= $this->serviceRow($service, $index, count($list), $domains);
            }
            if ([] === $list) {
                $body .= '<tr><td colspan="' . $columns . '" class="ck-empty">' . rex_i18n::msg('consent_kit_group_empty') . '</td></tr>';
            }
        }

        $onboarding = '';
        if (count($services) <= 1) {
            $onboarding = '<section class="ck-onboarding" aria-labelledby="ck-onboarding-title"><h2 id="ck-onboarding-title">' . rex_i18n::msg('consent_kit_onboarding_title') . '</h2><ol>'
                . '<li>' . rex_i18n::rawMsg('consent_kit_onboarding_1', rex_url::backendPage('consent_kit/settings')) . '</li>'
                . '<li>' . rex_i18n::rawMsg('consent_kit_onboarding_2', rex_url::backendPage('consent_kit/services', ['func' => 'add'])) . '</li>'
                . '<li>' . rex_i18n::rawMsg('consent_kit_onboarding_3', rex_url::backendPage('consent_kit/design')) . '</li></ol></section>';
        }

        return $onboarding . $this->checklist($services)
            . '<div class="ck-toolbar">'
            . '<a class="btn btn-save" href="' . rex_url::backendPage('consent_kit/services', ['func' => 'add']) . '"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_service_add') . '</a> '
            . '<a class="btn btn-default" href="' . rex_url::backendPage('consent_kit/services', ['func' => 'group']) . '"><i class="rex-icon fa-folder-o" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_group_add') . '</a>'
            . ([] !== $domains ? '<span class="ck-toolbar-hint"><i class="rex-icon fa-info-circle" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_matrix_hint') . '</span>' : '')
            . '</div>'
            . '<div class="panel panel-default ck-matrix-panel" id="ck-overview"><table class="table table-hover ck-matrix"><thead>' . $head . '</thead><tbody>' . $body . '</tbody></table></div>';
    }

    /**
     * @param array<string, mixed> $service
     * @param array<int, string> $domains
     */
    private function serviceRow(array $service, int $index, int $total, array $domains): string
    {
        $name = $service['name'];
        $status = $this->postButton(
            'status',
            ['id' => $service['id'], 'status' => $service['status'] ? 0 : 1],
            '<span class="ck-switch' . ($service['status'] ? ' is-on' : '') . '" aria-hidden="true"></span><span class="sr-only">' . rex_escape($name) . ': ' . rex_i18n::msg($service['status'] ? 'consent_kit_active' : 'consent_kit_inactive') . '</span>',
            'ck-switch-button',
            '',
            ['role' => 'switch', 'aria-checked' => $service['status'] ? 'true' : 'false', 'data-focus' => 'status-' . $service['id']],
        );

        $badges = '';
        if (!Cache::isComplete($service)) {
            $badges .= ' <span class="label label-warning" title="' . rex_i18n::msg('consent_kit_incomplete_hint') . '">' . rex_i18n::msg('consent_kit_incomplete') . '</span>';
        }
        if ([] !== $service['variants']) {
            $badges .= ' <span class="label label-info" title="' . rex_i18n::msg('consent_kit_variants_hint') . '">' . rex_i18n::msg('consent_kit_variants_count', (string) count($service['variants'])) . '</span>';
        }
        foreach ($service['gcm_signals'] as $signal) {
            $badges .= ' <code class="ck-signal">' . rex_escape($signal) . '</code>';
        }

        $cells = '';
        foreach ($domains as $domainId => $host) {
            $on = [] === $service['domain_ids'] || in_array($domainId, $service['domain_ids'], true);
            $cells .= '<td class="ck-col-domain">' . $this->postButton(
                'domain',
                ['id' => $service['id'], 'domain' => $domainId],
                '<i class="rex-icon ' . ($on ? 'fa-check-circle' : 'fa-circle-thin') . '" aria-hidden="true"></i><span class="sr-only">' . rex_escape($name . ' – ' . $host) . '</span>',
                'ck-cell-button' . ($on ? ' is-on' : ''),
                '',
                ['role' => 'switch', 'aria-checked' => $on ? 'true' : 'false', 'data-focus' => 'domain-' . $service['id'] . '-' . $domainId],
            ) . '</td>';
        }

        $edit = rex_url::backendPage('consent_kit/services', ['func' => 'edit', 'id' => $service['id']]);
        $description = I18n::pick($service['description'], $this->lang);
        return '<tr' . ($service['status'] ? '' : ' class="ck-off"') . '>'
            . '<td class="ck-col-status">' . $status . '</td>'
            . '<td class="ck-col-service"><a href="' . $edit . '" class="ck-service-name" title="' . rex_escape($description) . '">' . rex_escape($name) . '</a> <code>' . rex_escape($service['key']) . '</code>' . $badges . '</td>'
            . $cells
            . '<td class="ck-col-entries">' . ([] === $service['items'] ? '<span class="text-muted">–</span>' : count($service['items'])) . '</td>'
            . '<td class="ck-col-actions"><div class="ck-actions">'
            . $this->moveButtons('service', $service['id'], $index, $total, $name)
            . '<a class="btn btn-default btn-xs" href="' . $edit . '" title="' . rex_i18n::msg('consent_kit_edit') . '"><i class="rex-icon fa-pencil" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_edit') . ': ' . rex_escape($name) . '</span></a>'
            . $this->postButton('delete', ['id' => $service['id']], '<i class="rex-icon fa-trash-o" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_delete') . ': ' . rex_escape($name) . '</span>', 'btn-default btn-xs', rex_i18n::msg('consent_kit_confirm_delete', $name))
            . '</div></td></tr>';
    }

    /** @param list<array<string, mixed>> $services */
    private function checklist(array $services): string
    {
        $addon = rex_addon::get('consent_kit');
        $checks = [];

        $checks[] = $addon->getConfig('auto_inject', true)
            ? ['ok', rex_i18n::msg('consent_kit_check_inject_on')]
            : ['info', rex_i18n::rawMsg('consent_kit_check_inject_off')];

        $fallback = null;
        foreach (Repository::domains() as $domain) {
            if ('*' === $domain['host']) {
                $fallback = $domain;
            }
        }
        $settingsUrl = rex_url::backendPage('consent_kit/settings');
        $checks[] = null !== $fallback && $fallback['privacy_article_id'] > 0
            ? ['ok', rex_i18n::msg('consent_kit_check_privacy_ok')]
            : ['warn', rex_i18n::rawMsg('consent_kit_check_privacy_missing', $settingsUrl)];
        $checks[] = null !== $fallback && $fallback['imprint_article_id'] > 0
            ? ['ok', rex_i18n::msg('consent_kit_check_imprint_ok')]
            : ['warn', rex_i18n::rawMsg('consent_kit_check_imprint_missing', $settingsUrl)];

        // Ein erzwungener Dialog (ohne x) weicht auf Datenschutz/Impressum auf die Box aus – auf einer Startseite ist das fast nie gewollt.
        $startIds = [rex_article::getSiteStartArticleId()];
        if (rex_addon::get('yrewrite')->isAvailable()) {
            foreach (rex_yrewrite::getDomains() as $yrewriteDomain) {
                $startIds[] = $yrewriteDomain->getStartId();
            }
        }
        $forcedDialog = 'modal' === $addon->getConfig('layout') && !$addon->getConfig('dismissible', true);
        foreach ($forcedDialog ? Repository::domains() : [] as $domain) {
            foreach (['privacy_article_id' => 'consent_kit_domain_privacy', 'imprint_article_id' => 'consent_kit_domain_imprint'] as $field => $label) {
                if ($domain[$field] > 0 && in_array($domain[$field], $startIds, true)) {
                    $checks[] = ['warn', rex_i18n::rawMsg('consent_kit_check_legal_is_start', rex_i18n::msg($label), $settingsUrl)];
                }
            }
        }

        $optional = 0;
        $incomplete = [];
        $requiredGroups = array_column(array_filter(Repository::groups(), static fn (array $g) => $g['required']), 'id');
        foreach ($services as $service) {
            if (!$service['status']) {
                continue;
            }
            if (!Cache::isComplete($service)) {
                $incomplete[] = $service['name'];
            } elseif (!in_array($service['group_id'], $requiredGroups, true)) {
                ++$optional;
            }
        }
        $checks[] = $optional > 0
            ? ['ok', rex_i18n::msg('consent_kit_check_services', (string) $optional)]
            : ['info', rex_i18n::msg('consent_kit_check_no_services')];
        if ([] !== $incomplete) {
            $checks[] = ['warn', rex_i18n::msg('consent_kit_check_incomplete', implode(', ', $incomplete))];
        }

        $items = '';
        $icons = ['ok' => 'fa-check-circle', 'warn' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
        $labels = ['ok' => 'consent_kit_check_label_ok', 'warn' => 'consent_kit_check_label_warn', 'info' => 'consent_kit_check_label_info'];
        foreach ($checks as [$state, $text]) {
            $items .= '<li class="ck-check-' . $state . '"><i class="rex-icon ' . $icons[$state] . '" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg($labels[$state]) . ': </span><span>' . $text . '</span></li>';
        }
        return '<section class="ck-checklist" aria-labelledby="ck-checklist-title"><h2 id="ck-checklist-title">' . rex_i18n::msg('consent_kit_checklist') . '</h2><ul>' . $items . '</ul></section>';
    }

    private function moveButtons(string $type, int $id, int $index, int $total, string $name): string
    {
        $out = '';
        foreach ([-1 => ['fa-arrow-up', 'consent_kit_move_up', 0 === $index], 1 => ['fa-arrow-down', 'consent_kit_move_down', $index === $total - 1]] as $direction => [$icon, $label, $disabled]) {
            $out .= $this->postButton(
                'move',
                ['type' => $type, 'id' => $id, 'direction' => $direction],
                '<i class="rex-icon ' . $icon . '" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg($label) . ': ' . rex_escape($name) . '</span>',
                'btn-default btn-xs',
                '',
                ['data-focus' => 'move-' . $type . '-' . $id . '-' . $direction] + ($disabled ? ['disabled' => true] : []),
            );
        }
        return $out;
    }

    /**
     * Aenderungen laufen immer per POST mit CSRF-Token, nie per Link.
     *
     * @param array<string, string|int> $fields
     * @param array<string, string|bool> $attributes
     */
    private function postButton(string $func, array $fields, string $label, string $class, string $confirm = '', array $attributes = []): string
    {
        $hidden = '';
        foreach ($fields as $name => $value) {
            $hidden .= '<input type="hidden" name="' . rex_escape($name) . '" value="' . rex_escape((string) $value) . '">';
        }
        if ('' !== $confirm) {
            $attributes['data-confirm'] = $confirm;
        }
        return '<form class="ck-inline-form" method="post" action="' . rex_url::backendPage('consent_kit/services', ['func' => $func]) . '">'
            . $this->csrf->getHiddenField() . $hidden
            . '<button type="submit" class="' . (str_starts_with($class, 'ck-') ? $class : 'btn ' . $class) . '"' . Form::attributes($attributes) . '>' . $label . '</button></form>';
    }

    /* ---------------------------------------------------------- Vorlagen */

    private function presetPicker(): string
    {
        $existing = array_column(Repository::services(), 'key');
        $groupNames = [];
        foreach (Repository::groups() as $group) {
            $groupNames[$group['key']] = I18n::pick($group['name'], $this->lang);
        }

        $filters = '<button type="button" class="btn btn-default active" data-ck-filter="" aria-pressed="true">' . rex_i18n::msg('consent_kit_all') . '</button>';
        foreach ($groupNames as $key => $name) {
            $filters .= '<button type="button" class="btn btn-default" data-ck-filter="' . rex_escape($key) . '" aria-pressed="false">' . rex_escape($name) . '</button>';
        }

        $cards = '<li class="ck-preset ck-preset-custom" data-group="" data-search=""><h3>' . rex_i18n::msg('consent_kit_custom_service') . '</h3><p>' . rex_i18n::msg('consent_kit_custom_service_text') . '</p>'
            . '<a class="btn btn-default" href="' . rex_url::backendPage('consent_kit/services', ['func' => 'edit']) . '">' . rex_i18n::msg('consent_kit_custom_service_create') . '</a></li>';
        foreach (PresetRepository::all() as $key => $preset) {
            $group = (string) ($preset['group'] ?? '');
            $description = I18n::pick(Form::stringMap($preset['description'] ?? []), $this->lang);
            $itemCount = count((array) ($preset['items'] ?? []));
            $meta = '<span class="label label-default">' . rex_escape($groupNames[$group] ?? $group) . '</span> '
                . '<span class="text-muted">' . (1 === $itemCount ? rex_i18n::msg('consent_kit_entries_count_one') : rex_i18n::msg('consent_kit_entries_count', (string) $itemCount)) . '</span>';
            $action = in_array($key, $existing, true)
                ? '<span class="text-muted"><i class="rex-icon fa-check" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_preset_exists') . '</span>'
                : $this->postButton('preset', ['preset' => $key], rex_i18n::msg('consent_kit_add') . '<span class="sr-only">: ' . rex_escape((string) $preset['name']) . '</span>', 'btn-save');
            $cards .= '<li class="ck-preset" data-group="' . rex_escape($group) . '" data-search="' . rex_escape(mb_strtolower($preset['name'] . ' ' . $key . ' ' . ($preset['provider'] ?? ''))) . '">'
                . '<h3>' . rex_escape((string) $preset['name']) . '</h3><p class="ck-preset-meta">' . $meta . '</p><p>' . rex_escape($description) . '</p>' . $action . '</li>';
        }

        $body = '<div class="ck-preset-tools"><div class="ck-preset-search"><label for="ck-preset-search">' . rex_i18n::msg('consent_kit_preset_search') . '</label>'
            . '<input type="search" class="form-control" id="ck-preset-search" autocomplete="off" autofocus></div>'
            . '<div class="btn-group" role="group" aria-label="' . rex_i18n::msg('consent_kit_group') . '">' . $filters . '</div></div>'
            . '<p class="sr-only" role="status" aria-live="polite" id="ck-preset-status"></p>'
            . '<ul class="ck-presets" data-status-template="' . rex_i18n::msg('consent_kit_preset_results') . '">' . $cards . '</ul>'
            . '<p class="help-block">' . rex_i18n::msg('consent_kit_preset_disclaimer') . '</p>';

        return $this->section(rex_i18n::msg('consent_kit_service_add'), $body, $this->backButton());
    }

    /* -------------------------------------------------------- Formulare */

    private function serviceForm(int $id): string
    {
        $service = $id > 0 ? Repository::service($id) : null;
        if ($id > 0 && null === $service) {
            return rex_view::error(rex_i18n::msg('consent_kit_not_found'));
        }
        $posted = rex_request::post('service', 'array', []);
        $service ??= [
            'id' => 0, 'key' => '', 'group_id' => 0, 'status' => true, 'name' => '', 'provider' => '', 'privacy_url' => '',
            'description' => [], 'params' => [], 'html_head' => '', 'html_body' => '', 'js_default' => '', 'js_accept' => '', 'js_revoke' => '',
            'gcm_signals' => [], 'embed_hosts' => [], 'domain_ids' => [], 'preset' => '', 'items' => [], 'variants' => [],
        ];
        // Nach einem Validierungsfehler die Eingaben behalten.
        if ([] !== $posted) {
            foreach (['key', 'name', 'provider', 'privacy_url', 'html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke'] as $field) {
                $service[$field] = (string) ($posted[$field] ?? '');
            }
            $service['group_id'] = (int) ($posted['group_id'] ?? 0);
            $service['status'] = !empty($posted['status']);
            $service['description'] = Form::stringMap($posted['description'] ?? []);
            $service['params'] = Form::stringMap($posted['params'] ?? []);
            $service['gcm_signals'] = array_map('strval', (array) ($posted['gcm_signals'] ?? []));
            $service['domain_ids'] = 'selected' === ($posted['domain_mode'] ?? 'all') ? array_map('intval', (array) ($posted['domain_ids'] ?? [])) ?: [-1] : [];
            $service['embed_hosts'] = preg_split('~[\s,]+~', (string) ($posted['embed_hosts'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $service['variants'] = [];
            foreach (rex_request::post('variants', 'array', []) as $variant) {
                if (is_array($variant)) {
                    $entry = ['domain_id' => (int) ($variant['domain_id'] ?? 0), 'clang' => (string) ($variant['clang'] ?? ''), 'params' => Form::stringMap($variant['params'] ?? [])];
                    foreach (Repository::CODE_FIELDS as $field) {
                        $entry[$field] = (string) ($variant[$field] ?? '');
                    }
                    $service['variants'][] = $entry;
                }
            }
            $service['items'] = [];
            foreach (rex_request::post('items', 'array', []) as $item) {
                if (is_array($item)) {
                    $service['items'][] = [
                        'type' => (string) ($item['type'] ?? 'cookie'), 'name' => (string) ($item['name'] ?? ''), 'host' => (string) ($item['host'] ?? ''),
                        'duration_value' => (int) ($item['duration_value'] ?? 0), 'duration_unit' => (string) ($item['duration_unit'] ?? 'session'),
                        'purpose' => Form::stringMap($item['purpose'] ?? []),
                    ];
                }
            }
        }

        $groupOptions = [];
        foreach (Repository::groups() as $group) {
            $groupOptions[$group['id']] = I18n::pick($group['name'], $this->lang);
        }

        // Allgemein
        $general = '';
        $paramDefinitions = PresetRepository::params($service['preset']);
        if ([] !== $paramDefinitions) {
            $general .= '<div class="ck-params"><h3>' . rex_i18n::msg('consent_kit_params') . '</h3><p class="help-block">' . rex_i18n::msg('consent_kit_params_help') . '</p>';
            $focused = false;
            foreach ($paramDefinitions as $param) {
                $value = $service['params'][$param['key']] ?? '';
                $attributes = ['placeholder' => $param['placeholder'], 'autocomplete' => 'off', 'spellcheck' => 'false'];
                if ('' !== $param['pattern']) {
                    // Das Muster prueft schon im Browser; der Server prueft in saveService() erneut.
                    $attributes['pattern'] = trim($param['pattern'], '^$');
                    $attributes['title'] = rex_i18n::msg('consent_kit_param_format', $param['placeholder']);
                }
                $invalid = '' !== $value && '' !== $param['pattern'] && 1 !== @preg_match('~' . str_replace('~', '\~', $param['pattern']) . '~', $value);
                if ($invalid) {
                    $attributes['aria-invalid'] = 'true';
                }
                if (!$focused && ('' === $value || $invalid)) {
                    $attributes['autofocus'] = true;
                    $focused = true;
                }
                $general .= Form::text('service[params][' . $param['key'] . ']', I18n::pick($param['label'], $this->lang), $value, '' !== $param['placeholder'] ? rex_i18n::msg('consent_kit_param_example', $param['placeholder']) : '', $attributes);
            }
            $general .= '</div>';
        }
        $general .= '<div class="row"><div class="col-md-6">'
            . Form::text('service[name]', rex_i18n::msg('consent_kit_name'), $service['name'], '', ['required' => true])
            . '</div><div class="col-md-6">'
            . Form::text('service[key]', rex_i18n::msg('consent_kit_key'), $service['key'], rex_i18n::rawMsg('consent_kit_key_help', rex_escape('' !== $service['key'] ? $service['key'] : 'matomo')), ['required' => true, 'pattern' => '[a-z0-9_]{2,64}', 'spellcheck' => 'false', 'autocomplete' => 'off'])
            . '</div></div><div class="row"><div class="col-md-6">'
            . Form::select('service[group_id]', rex_i18n::msg('consent_kit_group'), $service['group_id'], $groupOptions)
            . '</div><div class="col-md-6 ck-status-field">'
            . Form::checkbox('service[status]', rex_i18n::msg('consent_kit_active'), $service['status'], rex_i18n::msg('consent_kit_status_help'))
            . '</div></div>'
            . Form::i18n('service[description]', rex_i18n::msg('consent_kit_description'), $service['description'], true, rex_i18n::msg('consent_kit_description_help'))
            . Form::textarea('service[provider]', rex_i18n::msg('consent_kit_provider'), $service['provider'], rex_i18n::msg('consent_kit_provider_help'), ['rows' => 2])
            . Form::text('service[privacy_url]', rex_i18n::msg('consent_kit_privacy_url'), $service['privacy_url'], '', ['type' => 'url', 'placeholder' => 'https://']);

        $domains = array_filter(Repository::domains(), static fn (array $d) => '*' !== $d['host']);
        $general .= '<fieldset class="ck-i18n ck-domain-select"><legend>' . rex_i18n::msg('consent_kit_domains_restrict') . '</legend>';
        if ([] === $domains) {
            $general .= '<p class="help-block">' . rex_i18n::rawMsg('consent_kit_domains_none', rex_url::backendPage('consent_kit/settings')) . '</p>';
        } else {
            $selected = [] !== $service['domain_ids'];
            $general .= '<div class="ck-choice-grid" data-ck-domain-mode>';
            foreach (['all' => ['consent_kit_domains_mode_all', 'consent_kit_domains_mode_all_text'], 'selected' => ['consent_kit_domains_mode_selected', 'consent_kit_domains_mode_selected_text']] as $mode => [$label, $text]) {
                $id = Form::id();
                $general .= '<label class="ck-choice-card" for="' . $id . '"><input type="radio" id="' . $id . '" name="service[domain_mode]" value="' . $mode . '"' . (('selected' === $mode) === $selected ? ' checked' : '') . '>'
                    . '<span class="ck-choice-label">' . rex_i18n::msg($label) . '</span><span class="ck-choice-text">' . rex_i18n::msg($text) . '</span></label>';
            }
            $general .= '</div><div class="ck-domain-list ck-signal-grid" data-ck-domain-list' . ($selected ? '' : ' hidden') . '>';
            foreach ($domains as $domain) {
                $general .= Form::checkbox('service[domain_ids][]', $domain['host'], in_array($domain['id'], $service['domain_ids'], true), '', (string) $domain['id']);
            }
            $general .= '</div>';
        }
        $general .= '</fieldset>';

        // Cookies & Speicher
        $itemRows = '';
        foreach ($service['items'] as $index => $item) {
            $itemRows .= $this->itemRow((string) $index, $item);
        }
        $items = '<p class="help-block">' . rex_i18n::msg('consent_kit_items_help') . '</p>'
            . '<div class="ck-items" data-ck-repeater="item">' . $itemRows . '</div>'
            . '<template id="ck-item-template">' . $this->itemRow('__INDEX__', ['type' => 'cookie', 'name' => '', 'host' => '', 'duration_value' => 0, 'duration_unit' => 'session', 'purpose' => []]) . '</template>'
            . '<button type="button" class="btn btn-default" data-ck-repeater-add="item"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_item_add') . '</button>';

        // Scripts
        $code = ['class' => 'ck-code', 'rows' => 6, 'spellcheck' => 'false', 'autocapitalize' => 'off', 'autocomplete' => 'off'];
        $scripts = '<p class="help-block">' . rex_i18n::rawMsg('consent_kit_scripts_help') . '</p>'
            . Form::textarea('service[html_head]', rex_i18n::msg('consent_kit_html_head'), $service['html_head'], rex_i18n::msg('consent_kit_html_head_help'), $code)
            . Form::textarea('service[html_body]', rex_i18n::msg('consent_kit_html_body'), $service['html_body'], rex_i18n::msg('consent_kit_html_body_help'), $code)
            . Form::textarea('service[js_accept]', rex_i18n::msg('consent_kit_js_accept'), $service['js_accept'], rex_i18n::msg('consent_kit_js_accept_help'), ['rows' => 3] + $code)
            . Form::textarea('service[js_revoke]', rex_i18n::msg('consent_kit_js_revoke'), $service['js_revoke'], rex_i18n::msg('consent_kit_js_revoke_help'), ['rows' => 3] + $code)
            . Form::textarea('service[js_default]', rex_i18n::msg('consent_kit_js_default'), $service['js_default'], rex_i18n::msg('consent_kit_js_default_help'), ['rows' => 3] + $code);

        // Varianten
        $emptyVariant = ['domain_id' => 0, 'clang' => '', 'params' => []] + array_fill_keys(Repository::CODE_FIELDS, '');
        $variantRows = '';
        foreach ($service['variants'] as $index => $variant) {
            $variantRows .= $this->variantRow((string) $index, $variant, $paramDefinitions);
        }
        $variantsTab = '<p class="help-block">' . rex_i18n::rawMsg('consent_kit_variants_help') . '</p>'
            . '<div class="ck-items" data-ck-repeater="variant">' . $variantRows . '</div>'
            . '<template id="ck-variant-template">' . $this->variantRow('__INDEX__', ['domain_id' => (int) (array_values(array_filter(Repository::domains(), static fn (array $d) => '*' !== $d['host']))[0]['id'] ?? 0)] + $emptyVariant, $paramDefinitions) . '</template>'
            . '<button type="button" class="btn btn-default" data-ck-repeater-add="variant"><i class="rex-icon fa-plus" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_variant_add') . '</button>';

        // Erweitert
        $advanced = '<fieldset class="ck-i18n"><legend>' . rex_i18n::msg('consent_kit_gcm_signals') . '</legend><div class="ck-signal-grid">';
        foreach (Repository::GCM_SIGNALS as $signal) {
            $advanced .= Form::checkbox('service[gcm_signals][]', $signal, in_array($signal, $service['gcm_signals'], true), '', $signal);
        }
        $advanced .= '</div><p class="help-block">' . rex_i18n::msg('consent_kit_gcm_signals_help') . '</p></fieldset>'
            . Form::textarea('service[embed_hosts]', rex_i18n::msg('consent_kit_embed_hosts'), implode("\n", $service['embed_hosts']), rex_i18n::rawMsg('consent_kit_embed_hosts_help'), ['rows' => 3, 'class' => 'ck-code', 'spellcheck' => 'false', 'placeholder' => "youtube.com\nyoutube-nocookie.com"]);

        $tabs = [
            'general' => [rex_i18n::msg('consent_kit_tab_general'), $general],
            'items' => [rex_i18n::msg('consent_kit_tab_items') . ' <span class="badge" data-ck-count="item">' . count($service['items']) . '</span>', $items],
            'scripts' => [rex_i18n::msg('consent_kit_tab_scripts'), $scripts],
            'variants' => [rex_i18n::msg('consent_kit_tab_variants') . ' <span class="badge" data-ck-count="variant">' . count($service['variants']) . '</span>', $variantsTab],
            'advanced' => [rex_i18n::msg('consent_kit_tab_advanced'), $advanced],
        ];
        $nav = '';
        $panes = '';
        $first = true;
        foreach ($tabs as $key => [$label, $content]) {
            $nav .= '<li role="presentation"' . ($first ? ' class="active"' : '') . '><a href="#ck-tab-' . $key . '" id="ck-tablink-' . $key . '" role="tab" data-toggle="tab" aria-controls="ck-tab-' . $key . '" aria-selected="' . ($first ? 'true' : 'false') . '">' . $label . '</a></li>';
            $panes .= '<div role="tabpanel" class="tab-pane' . ($first ? ' active' : '') . '" id="ck-tab-' . $key . '" aria-labelledby="ck-tablink-' . $key . '">' . $content . '</div>';
            $first = false;
        }

        $buttons = '<button type="submit" class="btn btn-save" name="save_and_close" value="1">' . rex_i18n::msg('consent_kit_save_close') . '</button> '
            . '<button type="submit" class="btn btn-apply">' . rex_i18n::msg('consent_kit_apply') . '</button> '
            . '<a class="btn btn-abort" href="' . rex_url::backendPage('consent_kit/services') . '">' . rex_i18n::msg('consent_kit_cancel') . '</a>';

        $form = '<form method="post" action="' . rex_url::backendPage('consent_kit/services', ['func' => 'save']) . '" class="ck-form">'
            . $this->csrf->getHiddenField()
            . '<input type="hidden" name="id" value="' . (int) $service['id'] . '">'
            . '<input type="hidden" name="service[preset]" value="' . rex_escape($service['preset']) . '">'
            . '<ul class="nav nav-tabs" role="tablist">' . $nav . '</ul><div class="tab-content ck-tab-content">' . $panes . '</div>'
            . ('' !== WriteAssist::bulkButton() ? '<div class="ck-toolbar">' . WriteAssist::bulkButton() . '<span class="ck-toolbar-hint" data-ck-translate-progress></span></div>' : '')
            . '<footer class="ck-form-footer">' . $buttons . '</footer></form>';

        $extra = '';
        $preset = PresetRepository::get($service['preset']);
        if ($service['id'] > 0 && null !== $preset) {
            $sources = '';
            foreach ((array) ($preset['sources'] ?? []) as $source) {
                if (is_string($source) && 1 === preg_match('~^https?://~', $source)) {
                    $sources .= '<li><a href="' . rex_escape($source) . '" target="_blank" rel="noopener noreferrer">' . rex_escape($source) . '</a></li>';
                }
            }
            $note = I18n::pick(Form::stringMap($preset['note'] ?? []), $this->lang);
            $extra = '<aside class="ck-preset-info"><h3>' . rex_i18n::msg('consent_kit_from_preset') . '</h3>'
                . ('' !== $note ? '<p class="ck-note ck-note-warn"><i class="rex-icon fa-exclamation-triangle" aria-hidden="true"></i> ' . rex_escape($note) . '</p>' : '') . '<p>' . rex_i18n::msg('consent_kit_preset_verified', (string) ($preset['verified'] ?? '–')) . '</p>'
                . ('' !== $sources ? '<p>' . rex_i18n::msg('consent_kit_preset_sources') . '</p><ul>' . $sources . '</ul>' : '')
                . $this->postButton('reset', ['id' => $service['id']], rex_i18n::msg('consent_kit_preset_reset'), 'btn-default', rex_i18n::msg('consent_kit_preset_reset_confirm')) . '</aside>';
        }

        $title = $service['id'] > 0 ? rex_i18n::msg('consent_kit_service_edit', $service['name']) : rex_i18n::msg('consent_kit_custom_service_create');
        return $this->section($title, $form . $extra, $this->backButton());
    }

    /** @param array<string, mixed> $item */
    private function itemRow(string $index, array $item): string
    {
        $types = [];
        foreach (Repository::ITEM_TYPES as $type) {
            $types[$type] = rex_i18n::msg('consent_kit_type_' . $type);
        }
        $units = [];
        foreach (Repository::DURATION_UNITS as $unit) {
            $units[$unit] = rex_i18n::msg('consent_kit_unit_' . $unit);
        }
        $name = 'items[' . $index . ']';
        return '<fieldset class="ck-item" data-ck-row><legend class="sr-only">' . rex_i18n::msg('consent_kit_item') . '</legend>'
            . '<div class="ck-item-grid">'
            . Form::select($name . '[type]', rex_i18n::msg('consent_kit_item_type'), (string) $item['type'], $types)
            . Form::text($name . '[name]', rex_i18n::msg('consent_kit_item_name'), (string) $item['name'], '', ['spellcheck' => 'false', 'autocomplete' => 'off', 'placeholder' => '_ga_*'])
            . Form::text($name . '[host]', rex_i18n::msg('consent_kit_item_host'), (string) $item['host'], '', ['spellcheck' => 'false', 'autocomplete' => 'off', 'placeholder' => rex_i18n::msg('consent_kit_item_host_placeholder')])
            . Form::text($name . '[duration_value]', rex_i18n::msg('consent_kit_item_duration'), (string) $item['duration_value'], '', ['type' => 'number', 'min' => '0', 'inputmode' => 'numeric'])
            . Form::select($name . '[duration_unit]', rex_i18n::msg('consent_kit_item_unit'), (string) $item['duration_unit'], $units)
            . '</div>'
            . Form::i18n($name . '[purpose]', rex_i18n::msg('consent_kit_item_purpose'), (array) $item['purpose'])
            . '<button type="button" class="btn btn-default btn-xs ck-item-remove" data-ck-row-remove><i class="rex-icon fa-trash-o" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_item_remove') . '</button>'
            . '</fieldset>';
    }

    /**
     * @param array<string, mixed> $variant
     * @param list<array{key: string, label: array<string, string>, placeholder: string, pattern: string}> $paramDefinitions
     */
    private function variantRow(string $index, array $variant, array $paramDefinitions): string
    {
        $domainOptions = [0 => rex_i18n::msg('consent_kit_variant_all_domains')];
        foreach (Repository::domains() as $domain) {
            if ('*' !== $domain['host']) {
                $domainOptions[$domain['id']] = $domain['host'];
            }
        }
        $clangOptions = ['' => rex_i18n::msg('consent_kit_variant_all_languages')];
        foreach (I18n::languages() as $code => $language) {
            $clangOptions[$code] = $language . ' (' . strtoupper($code) . ')';
        }
        $name = 'variants[' . $index . ']';
        $out = '<fieldset class="ck-item ck-variant" data-ck-row><legend class="sr-only">' . rex_i18n::msg('consent_kit_variant') . '</legend><div class="ck-variant-scope">'
            . Form::select($name . '[domain_id]', rex_i18n::msg('consent_kit_variant_domain'), (int) $variant['domain_id'], $domainOptions)
            . Form::select($name . '[clang]', rex_i18n::msg('consent_kit_variant_language'), (string) $variant['clang'], $clangOptions)
            . '</div>';
        if ([] !== $paramDefinitions) {
            $out .= '<div class="ck-variant-params">';
            foreach ($paramDefinitions as $param) {
                $out .= Form::text($name . '[params][' . $param['key'] . ']', I18n::pick($param['label'], $this->lang), (string) ($variant['params'][$param['key']] ?? ''), '', ['placeholder' => rex_i18n::msg('consent_kit_variant_inherit'), 'autocomplete' => 'off', 'spellcheck' => 'false']);
            }
            $out .= '</div>';
        }
        $hasCode = '' !== implode('', array_map(static fn (string $field) => (string) $variant[$field], Repository::CODE_FIELDS));
        // Ohne Vorlagen-Angaben ist eigener Code der einzige Inhalt einer Variante.
        $out .= '<details class="ck-variant-code"' . ($hasCode || [] === $paramDefinitions ? ' open' : '') . '><summary>' . rex_i18n::msg('consent_kit_variant_code') . '</summary><p class="help-block">' . rex_i18n::msg('consent_kit_variant_code_help') . '</p>';
        foreach (Repository::CODE_FIELDS as $field) {
            $out .= Form::textarea($name . '[' . $field . ']', rex_i18n::msg('consent_kit_' . $field), (string) $variant[$field], '', ['class' => 'ck-code', 'rows' => 3, 'spellcheck' => 'false', 'autocomplete' => 'off']);
        }
        return $out . '</details><button type="button" class="btn btn-default btn-xs ck-item-remove" data-ck-row-remove><i class="rex-icon fa-trash-o" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_variant_remove') . '</button></fieldset>';
    }

    private function groupForm(int $id): string
    {
        $group = $id > 0 ? Repository::group($id) : null;
        if ($id > 0 && null === $group) {
            return rex_view::error(rex_i18n::msg('consent_kit_not_found'));
        }
        $group ??= ['id' => 0, 'key' => '', 'required' => false, 'name' => [], 'description' => []];
        $posted = rex_request::post('group', 'array', []);
        if ([] !== $posted) {
            $group['key'] = (string) ($posted['key'] ?? '');
            $group['required'] = !empty($posted['required']);
            $group['name'] = Form::stringMap($posted['name'] ?? []);
            $group['description'] = Form::stringMap($posted['description'] ?? []);
        }

        $form = '<form method="post" action="' . rex_url::backendPage('consent_kit/services', ['func' => 'group_save']) . '" class="ck-form">'
            . $this->csrf->getHiddenField() . '<input type="hidden" name="id" value="' . (int) $group['id'] . '">'
            . Form::i18n('group[name]', rex_i18n::msg('consent_kit_name'), $group['name'])
            . Form::text('group[key]', rex_i18n::msg('consent_kit_key'), $group['key'], rex_i18n::msg('consent_kit_group_key_help'), ['required' => true, 'pattern' => '[a-z0-9_]{2,64}', 'spellcheck' => 'false'])
            . Form::i18n('group[description]', rex_i18n::msg('consent_kit_description'), $group['description'], true)
            . Form::checkbox('group[required]', rex_i18n::msg('consent_kit_required'), $group['required'], rex_i18n::msg('consent_kit_required_help'))
            . ('' !== WriteAssist::bulkButton() ? '<div class="ck-toolbar">' . WriteAssist::bulkButton() . '<span class="ck-toolbar-hint" data-ck-translate-progress></span></div>' : '')
            . '<footer class="ck-form-footer"><button type="submit" class="btn btn-save">' . rex_i18n::msg('consent_kit_save_close') . '</button> '
            . '<a class="btn btn-abort" href="' . rex_url::backendPage('consent_kit/services') . '">' . rex_i18n::msg('consent_kit_cancel') . '</a></footer></form>';

        return $this->section($group['id'] > 0 ? rex_i18n::msg('consent_kit_group_edit') : rex_i18n::msg('consent_kit_group_add'), $form, $this->backButton());
    }

    private function backButton(): string
    {
        return '<a class="btn btn-default btn-xs" href="' . rex_url::backendPage('consent_kit/services') . '"><i class="rex-icon fa-chevron-left" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_back') . '</a>';
    }

    private function section(string $title, string $body, string $options = ''): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('options', $options, false);
        $fragment->setVar('body', $body, false);
        return $fragment->parse('core/page/section.php');
    }
}
