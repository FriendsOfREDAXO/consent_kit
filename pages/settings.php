<?php

use KLXM\ConsentKit\Backend\Form;
use KLXM\ConsentKit\Cache;
use KLXM\ConsentKit\Repository;

$addon = rex_addon::get('consent_kit');
$csrf = rex_csrf_token::factory('consent_kit');
$message = '';
$checkboxes = ['auto_inject', 'trigger', 'dismissible', 'reload_on_revoke', 'block_embeds', 'gcm_ads_data_redaction', 'gcm_url_passthrough'];
Repository::syncYrewriteDomains();

if ('post' === rex_request::requestMethod()) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (rex_request::post('bump', 'bool', false)) {
        $addon->setConfig('revision_bump', (int) $addon->getConfig('revision_bump', 0) + 1);
        Cache::clear();
        $message = rex_view::success(rex_i18n::msg('consent_kit_bump_done'));
    } else {
        $settings = rex_request::post('settings', 'array', []);
        $choices = [
            'layout' => ['box', 'bar', 'modal'],
            'position' => ['bottom-left', 'bottom-right', 'top-left', 'top-right'],
            'theme' => ['light', 'dark', 'auto'],
            'trigger_position' => ['bottom-left', 'bottom-right'],
            'gpc' => ['reject', 'ask', 'ignore'],
            'gcm' => ['auto', 'off'],
        ];
        foreach ($choices as $key => $allowed) {
            if (in_array($settings[$key] ?? null, $allowed, true)) {
                $addon->setConfig($key, $settings[$key]);
            }
        }
        // Jedes Haekchen hat ein verstecktes 0-Feld. Fehlt der Schluessel ganz, stammt das Formular
        // aus einer aelteren Fassung der Seite – dann bleibt die Einstellung unangetastet.
        foreach ($checkboxes as $key) {
            if (array_key_exists($key, $settings)) {
                $addon->setConfig($key, !empty($settings[$key]));
            }
        }
        $addon->setConfig('consent_days', min(395, max(1, (int) ($settings['consent_days'] ?? 365))));
        $addon->setConfig('log_days', max(0, (int) ($settings['log_days'] ?? 1095)));
        $addon->setConfig('gcm_wait_for_update', min(5000, max(0, (int) ($settings['gcm_wait_for_update'] ?? 500))));

        $hosts = [];
        $errors = [];
        foreach (rex_request::post('domains', 'array', []) as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) $id;
            if (!empty($row['delete'])) {
                Repository::deleteDomain($id);
                continue;
            }
            $host = Repository::normalizeHost((string) ($row['host'] ?? ''));
            if ('' === $host) {
                continue;
            }
            if (isset($hosts[$host])) {
                $errors[] = rex_i18n::msg('consent_kit_domain_duplicate', $host);
                continue;
            }
            $hosts[$host] = true;
            Repository::saveDomain($id, $host, (int) ($row['privacy'] ?? 0), (int) ($row['imprint'] ?? 0));
        }
        Cache::clear();
        $message = [] === $errors
            ? rex_view::success(rex_i18n::msg('consent_kit_settings_saved'))
            : rex_view::warning(implode('<br>', $errors));
    }
}

$get = static fn (string $key, mixed $default = null) => $addon->getConfig($key, $default);
$panel = static function (string $title, string $body, string $intro = ''): string {
    $fragment = new rex_fragment();
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', ('' !== $intro ? '<p class="ck-panel-intro">' . $intro . '</p>' : '') . $body, false);
    return $fragment->parse('core/page/section.php');
};

// Darstellung
$display = Form::checkbox('settings[auto_inject]', rex_i18n::msg('consent_kit_auto_inject'), (bool) $get('auto_inject', true), rex_i18n::rawMsg('consent_kit_auto_inject_help'))
    . Form::choice('settings[layout]', rex_i18n::msg('consent_kit_layout'), (string) $get('layout', 'box'), [
        'box' => [rex_i18n::msg('consent_kit_layout_box'), rex_i18n::msg('consent_kit_layout_box_text')],
        'bar' => [rex_i18n::msg('consent_kit_layout_bar'), rex_i18n::msg('consent_kit_layout_bar_text')],
        'modal' => [rex_i18n::msg('consent_kit_layout_modal'), rex_i18n::msg('consent_kit_layout_modal_text')],
    ])
    . '<div class="row"><div class="col-md-6">'
    . Form::select('settings[position]', rex_i18n::msg('consent_kit_position'), (string) $get('position', 'bottom-left'), [
        'bottom-left' => rex_i18n::msg('consent_kit_pos_bottom_left'), 'bottom-right' => rex_i18n::msg('consent_kit_pos_bottom_right'),
        'top-left' => rex_i18n::msg('consent_kit_pos_top_left'), 'top-right' => rex_i18n::msg('consent_kit_pos_top_right'),
    ], rex_i18n::msg('consent_kit_position_help'))
    . '</div><div class="col-md-6">'
    . Form::select('settings[theme]', rex_i18n::msg('consent_kit_theme'), (string) $get('theme', 'light'), [
        'light' => rex_i18n::msg('consent_kit_theme_light'), 'dark' => rex_i18n::msg('consent_kit_theme_dark'), 'auto' => rex_i18n::msg('consent_kit_theme_auto'),
    ], rex_i18n::msg('consent_kit_theme_help'))
    . '</div></div>'
    . Form::checkbox('settings[dismissible]', rex_i18n::msg('consent_kit_dismissible'), (bool) $get('dismissible', true), rex_i18n::msg('consent_kit_dismissible_help'))
    . Form::checkbox('settings[trigger]', rex_i18n::msg('consent_kit_trigger'), (bool) $get('trigger', true), rex_i18n::rawMsg('consent_kit_trigger_help'))
    . '<div data-ck-show-if="settings[trigger]" data-ck-show-values="1">'
    . Form::select('settings[trigger_position]', rex_i18n::msg('consent_kit_trigger_position'), (string) $get('trigger_position', 'bottom-left'), [
        'bottom-left' => rex_i18n::msg('consent_kit_pos_bottom_left'), 'bottom-right' => rex_i18n::msg('consent_kit_pos_bottom_right'),
    ]) . '</div>';

// Einwilligung
$consent = Form::text('settings[consent_days]', rex_i18n::msg('consent_kit_consent_days'), (string) $get('consent_days', 365), rex_i18n::msg('consent_kit_consent_days_help'), ['type' => 'number', 'min' => '1', 'max' => '395'])
    . Form::checkbox('settings[reload_on_revoke]', rex_i18n::msg('consent_kit_reload'), (bool) $get('reload_on_revoke', true), rex_i18n::msg('consent_kit_reload_help'))
    . Form::checkbox('settings[block_embeds]', rex_i18n::msg('consent_kit_block_embeds'), (bool) $get('block_embeds', false), rex_i18n::rawMsg('consent_kit_block_embeds_help'))
    . Form::text('settings[log_days]', rex_i18n::msg('consent_kit_log_days'), (string) $get('log_days', 1095), rex_i18n::msg('consent_kit_log_days_help'), ['type' => 'number', 'min' => '0']);

// Browser-Signale
$signals = Form::choice('settings[gpc]', rex_i18n::msg('consent_kit_gpc'), (string) $get('gpc', 'reject'), [
    'reject' => [rex_i18n::msg('consent_kit_gpc_reject'), rex_i18n::msg('consent_kit_gpc_reject_text')],
    'ask' => [rex_i18n::msg('consent_kit_gpc_ask'), rex_i18n::msg('consent_kit_gpc_ask_text')],
    'ignore' => [rex_i18n::msg('consent_kit_gpc_ignore'), rex_i18n::msg('consent_kit_gpc_ignore_text')],
]);

// Google Consent Mode
$gcmServices = [];
foreach (Repository::services(true) as $service) {
    if ([] !== $service['gcm_signals']) {
        $gcmServices[] = $service['name'];
    }
}
$gcmStatus = [] === $gcmServices
    ? '<p class="ck-note">' . rex_i18n::msg('consent_kit_gcm_status_unused') . '</p>'
    : '<p class="ck-note ck-note-ok">' . rex_i18n::msg('consent_kit_gcm_status_used', implode(', ', $gcmServices)) . '</p>';
$gcm = $gcmStatus
    . Form::select('settings[gcm]', rex_i18n::msg('consent_kit_gcm'), (string) $get('gcm', 'auto'), [
        'auto' => rex_i18n::msg('consent_kit_gcm_auto'), 'off' => rex_i18n::msg('consent_kit_gcm_off'),
    ], rex_i18n::msg('consent_kit_gcm_help'))
    . '<details class="ck-details"><summary>' . rex_i18n::msg('consent_kit_gcm_advanced') . '</summary>'
    . Form::checkbox('settings[gcm_ads_data_redaction]', 'ads_data_redaction', (bool) $get('gcm_ads_data_redaction', true), rex_i18n::msg('consent_kit_gcm_redaction_help'))
    . Form::checkbox('settings[gcm_url_passthrough]', 'url_passthrough', (bool) $get('gcm_url_passthrough', false), rex_i18n::msg('consent_kit_gcm_passthrough_help'))
    . Form::text('settings[gcm_wait_for_update]', 'wait_for_update (ms)', (string) $get('gcm_wait_for_update', 500), rex_i18n::msg('consent_kit_gcm_wait_help'), ['type' => 'number', 'min' => '0', 'max' => '5000'])
    . '</details>';

// Domains
$domainRows = '';
$rows = Repository::domains();
$rows[] = ['id' => 0, 'host' => '', 'privacy_article_id' => 0, 'imprint_article_id' => 0];
foreach ($rows as $index => $domain) {
    $isFallback = '*' === $domain['host'];
    $isNew = 0 === $domain['id'];
    $name = 'domains[' . $domain['id'] . ']';
    $hostId = 'ck-domain-host-' . $index;
    $hostField = $isFallback
        ? '<input type="hidden" name="' . $name . '[host]" value="*"><strong>' . rex_i18n::msg('consent_kit_domain_fallback') . '</strong><p class="help-block">' . rex_i18n::msg('consent_kit_domain_fallback_help') . '</p>'
        : '<label class="sr-only" for="' . $hostId . '">' . rex_i18n::msg('consent_kit_domain_host') . '</label><input type="text" class="form-control" id="' . $hostId . '" name="' . $name . '[host]" value="' . rex_escape($domain['host']) . '" placeholder="' . ($isNew ? rex_i18n::msg('consent_kit_domain_new') : 'example.org') . '" spellcheck="false" autocomplete="off">';
    $delete = $isFallback || $isNew ? '' : Form::checkbox($name . '[delete]', rex_i18n::msg('consent_kit_delete'), false);
    $domainRows .= '<tr><td>' . $hostField . '</td>'
        . '<td>' . rex_var_link::getWidget(9000 + $index * 2, $name . '[privacy]', $domain['privacy_article_id']) . '</td>'
        . '<td>' . rex_var_link::getWidget(9001 + $index * 2, $name . '[imprint]', $domain['imprint_article_id']) . '</td>'
        . '<td>' . $delete . '</td></tr>';
}
$domains = '<table class="table ck-domains"><thead><tr><th>' . rex_i18n::msg('consent_kit_domain_host') . '</th><th>' . rex_i18n::msg('consent_kit_domain_privacy') . '</th><th>' . rex_i18n::msg('consent_kit_domain_imprint') . '</th><th><span class="sr-only">' . rex_i18n::msg('consent_kit_actions') . '</span></th></tr></thead><tbody>' . $domainRows . '</tbody></table>';

echo $message;
echo '<form method="post" action="' . rex_url::currentBackendPage() . '" class="ck-form ck-settings">' . $csrf->getHiddenField();
foreach ($checkboxes as $key) {
    echo '<input type="hidden" name="settings[' . $key . ']" value="0">';
}
echo $panel(rex_i18n::msg('consent_kit_section_domains'), $domains, rex_i18n::msg('consent_kit_section_domains_intro'));
echo $panel(rex_i18n::msg('consent_kit_section_display'), $display);
echo $panel(rex_i18n::msg('consent_kit_section_consent'), $consent);
echo $panel(rex_i18n::msg('consent_kit_section_signals'), $signals, rex_i18n::msg('consent_kit_section_signals_intro'));
echo $panel(rex_i18n::msg('consent_kit_section_gcm'), $gcm);
echo '<footer class="ck-form-footer ck-sticky-footer"><button type="submit" class="btn btn-save">' . rex_i18n::msg('consent_kit_save') . '</button></footer></form>';

$bump = '<p>' . rex_i18n::msg('consent_kit_bump_text') . '</p><form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
    . '<button type="submit" class="btn btn-default" name="bump" value="1" data-confirm="' . rex_i18n::msg('consent_kit_bump_confirm') . '"><i class="rex-icon fa-refresh" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_bump') . '</button></form>';
echo $panel(rex_i18n::msg('consent_kit_section_bump'), $bump);
