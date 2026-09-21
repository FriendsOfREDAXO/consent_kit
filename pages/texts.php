<?php

use KLXM\ConsentKit\Cache;
use KLXM\ConsentKit\I18n;
use KLXM\ConsentKit\Texts;

$addon = rex_addon::get('consent_kit');
$csrf = rex_csrf_token::factory('consent_kit');
$languages = I18n::languages();
$current = rex_request::request('lang', 'string', (string) array_key_first($languages));
if (!isset($languages[$current])) {
    $current = (string) array_key_first($languages);
}

// Reihenfolge und Gruppierung der Schluessel aus resources/texts.php.
$sections = [
    'banner' => ['title', 'intro', 'accept_all', 'reject_all', 'settings', 'close', 'gpc_notice', 'trigger'],
    'dialog' => ['settings_title', 'settings_intro', 'save', 'always_active', 'group_toggle', 'services_count', 'services_count_one', 'show_details', 'hide_details', 'consent_info'],
    'details' => ['provider', 'privacy_policy', 'privacy_policy_of', 'imprint', 'storage', 'no_items', 'col_name', 'col_type', 'col_host', 'col_duration', 'col_purpose', 'type_cookie', 'type_local_storage', 'type_session_storage', 'type_indexed_db'],
    'embed' => ['embed_title', 'embed_text', 'embed_once', 'embed_always', 'embed_settings'],
    'duration' => ['duration_session', 'duration_persistent', 'duration_minutes', 'duration_minutes_one', 'duration_hours', 'duration_hours_one', 'duration_days', 'duration_days_one', 'duration_months', 'duration_months_one', 'duration_years', 'duration_years_one'],
];

$message = '';
if ('post' === rex_request::requestMethod()) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } else {
        $all = (array) $addon->getConfig('texts', []);
        $defaults = Texts::defaultsFor($current);
        $all[$current] = [];
        foreach (rex_request::post('texts', 'array', []) as $key => $value) {
            $value = trim((string) $value);
            if (isset($defaults[$key]) && '' !== $value && $value !== $defaults[$key]) {
                $all[$current][$key] = $value;
            }
        }
        $addon->setConfig('texts', array_filter($all));
        Cache::clear();
        $message = rex_view::success(rex_i18n::msg('consent_kit_texts_saved'));
    }
}

$defaults = Texts::defaultsFor($current);
$overrides = (array) (((array) $addon->getConfig('texts', []))[$current] ?? []);

$tabs = '';
foreach ($languages as $code => $name) {
    $tabs .= '<li' . ($code === $current ? ' class="active"' : '') . '><a href="' . rex_url::currentBackendPage(['lang' => $code]) . '"' . ($code === $current ? ' aria-current="page"' : '') . '>' . rex_escape($name) . ' <small>' . rex_escape(strtoupper($code)) . '</small></a></li>';
}

$body = '';
if (!isset(Texts::defaults()[$current]) && !isset(Texts::defaults()[substr($current, 0, 2)])) {
    $body .= rex_view::info(rex_i18n::msg('consent_kit_texts_fallback', $languages[$current]));
}
foreach ($sections as $section => $keys) {
    $body .= '<fieldset class="ck-texts"><legend>' . rex_i18n::msg('consent_kit_texts_' . $section) . '</legend>';
    foreach ($keys as $key) {
        $id = 'ck-text-' . $key;
        $default = $defaults[$key];
        $value = (string) ($overrides[$key] ?? '');
        $long = mb_strlen($default) > 70;
        $control = $long
            ? '<textarea class="form-control" rows="3" id="' . $id . '" name="texts[' . $key . ']" placeholder="' . rex_escape($default) . '" lang="' . rex_escape(substr($current, 0, 2)) . '">' . rex_escape($value) . '</textarea>'
            : '<input type="text" class="form-control" id="' . $id . '" name="texts[' . $key . ']" value="' . rex_escape($value) . '" placeholder="' . rex_escape($default) . '" lang="' . rex_escape(substr($current, 0, 2)) . '">';
        $body .= '<div class="ck-text-row"><label for="' . $id . '">' . rex_i18n::msg('consent_kit_text_' . $key) . ' <code>' . $key . '</code></label>' . $control . '</div>';
    }
    $body .= '</fieldset>';
}

echo $message;
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('consent_kit_texts'), false);
$fragment->setVar('body', '<p class="ck-panel-intro">' . rex_i18n::rawMsg('consent_kit_texts_intro') . '</p><ul class="nav nav-tabs">' . $tabs . '</ul>'
    . '<form method="post" action="' . rex_url::currentBackendPage(['lang' => $current]) . '" class="ck-form ck-tab-content">' . $csrf->getHiddenField() . $body
    . '<footer class="ck-form-footer ck-sticky-footer"><button type="submit" class="btn btn-save">' . rex_i18n::msg('consent_kit_save') . '</button></footer></form>', false);
echo $fragment->parse('core/page/section.php');
