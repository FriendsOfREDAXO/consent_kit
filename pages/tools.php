<?php

use FriendsOfRedaxo\ConsentKit\Backend\BulkTranslator;
use FriendsOfRedaxo\ConsentKit\Backend\WriteAssist;
use FriendsOfRedaxo\ConsentKit\Catalog;
use FriendsOfRedaxo\ConsentKit\I18n;
use FriendsOfRedaxo\ConsentKit\Texts;
use FriendsOfRedaxo\ConsentKit\LegacyImporter;
use FriendsOfRedaxo\ConsentKit\PresetIo;
use FriendsOfRedaxo\ConsentKit\Repository;

$csrf = rex_csrf_token::factory('consent_kit');
$message = '';

$report = static function (array $result): string {
    $out = rex_view::success(rex_i18n::msg('consent_kit_legacy_done', (string) $result['groups'], (string) $result['services'], (string) $result['domains']));
    if ([] !== $result['notes']) {
        $out .= rex_view::warning('<strong>' . rex_i18n::msg('consent_kit_legacy_notes') . '</strong><ul><li>' . implode('</li><li>', array_map('rex_escape', $result['notes'])) . '</li></ul>');
    }
    return $out;
};

if ('post' === rex_request::requestMethod()) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (rex_request::post('catalog_import', 'bool', false)) {
        try {
            $message = rex_view::success(rex_i18n::msg('consent_kit_catalog_imported', (string) Catalog::import()));
        } catch (Throwable $exception) {
            $message = rex_view::error(rex_i18n::msg('consent_kit_catalog_failed', $exception->getMessage()));
        }
    } elseif (rex_request::post('bulk_translate', 'bool', false) && WriteAssist::available()) {
        $target = rex_request::post('target_lang', 'string', '');
        if (isset(BulkTranslator::targetLanguages()[$target])) {
            $stats = BulkTranslator::run($target, I18n::defaultCode());
            $message = rex_view::success(rex_i18n::msg('consent_kit_bulk_done', (string) $stats['translated'], (string) $stats['skipped'], (string) $stats['failed']));
        }
    } elseif (rex_request::post('preset_export', 'bool', false)) {
        $ids = array_values(array_filter(array_map('intval', rex_request::post('export_ids', 'array', []))));
        PresetIo::download(PresetIo::export($ids), 'consent-kit-presets-' . date('Y-m-d') . '.json');
    } elseif (rex_request::post('preset_import', 'bool', false)) {
        $file = rex_request::files('preset_json', 'array', []);
        if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            $message = rex_view::error(rex_i18n::msg('consent_kit_presets_import_none'));
        } else {
            $result = PresetIo::import(
                (string) rex_file::get((string) $file['tmp_name']),
                (string) ($file['name'] ?? 'presets.json'),
                rex_request::post('preset_overwrite', 'bool', false),
            );
            $message = $result['ok']
                ? rex_view::success(rex_i18n::msg('consent_kit_presets_import_done', (string) count($result['added']), rex_escape($result['file']), rex_escape(implode(', ', $result['added']))))
                : rex_view::error(rex_i18n::msg('consent_kit_presets_import_failed'));
            if ([] !== $result['errors']) {
                $message .= rex_view::warning('<strong>' . rex_i18n::msg('consent_kit_presets_import_partial') . '</strong><ul><li>' . implode('</li><li>', array_map('rex_escape', $result['errors'])) . '</li></ul>');
            }
        }
    } elseif ('' !== rex_request::post('preset_file_download', 'string', '')) {
        $name = PresetIo::safeName(rex_request::post('preset_file_download', 'string', ''));
        $content = rex_file::get(PresetIo::dir() . '/' . $name);
        if (null === $content) {
            $message = rex_view::error(rex_i18n::msg('consent_kit_presets_import_failed'));
        } else {
            PresetIo::download($content, $name);
        }
    } elseif ('' !== rex_request::post('preset_file_delete', 'string', '')) {
        $message = PresetIo::delete(rex_request::post('preset_file_delete', 'string', ''))
            ? rex_view::success(rex_i18n::msg('consent_kit_presets_deleted'))
            : rex_view::error(rex_i18n::msg('consent_kit_presets_delete_failed'));
    } elseif (rex_request::post('legacy_tables', 'bool', false) && LegacyImporter::tablesExist()) {
        $message = $report((new LegacyImporter())->fromTables());
    } elseif (rex_request::post('legacy_file', 'bool', false)) {
        $file = rex_request::files('legacy_json', 'array', []);
        $data = isset($file['tmp_name']) && is_uploaded_file((string) $file['tmp_name']) ? json_decode((string) rex_file::get((string) $file['tmp_name']), true) : null;
        $message = is_array($data) && isset($data['cookies'], $data['cookiegroups'])
            ? $report((new LegacyImporter())->fromArray($data))
            : rex_view::error(rex_i18n::msg('consent_kit_legacy_invalid'));
    }
}
echo $message;

$section = static function (string $title, string $body): string {
    $fragment = new rex_fragment();
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', $body, false);
    return $fragment->parse('core/page/section.php');
};

// Scanner
$frontendUrl = rtrim(rex::getServer(), '/') . '/';
$labels = [];
foreach (['scan_known', 'scan_inactive', 'scan_unknown', 'scan_catalog', 'scan_none', 'scan_cross_origin', 'scan_failed', 'type_cookie', 'type_local_storage', 'type_session_storage', 'scan_col_name', 'scan_col_type', 'scan_col_duration', 'scan_col_result', 'scan_documented', 'scan_deviates', 'scan_no_duration'] as $key) {
    // Das Script escaped selbst, deshalb hier die rohen Texte.
    $labels[$key] = rex_i18n::rawMsg('consent_kit_' . $key);
}
// Laufzeiten mit Ein-/Mehrzahl aus den Frontend-Texten.
foreach (Texts::all(rex_i18n::getLanguage()) as $key => $text) {
    if (str_starts_with($key, 'duration_')) {
        $labels[$key] = $text;
    }
}
$scanner = '<p class="ck-panel-intro">' . rex_i18n::msg('consent_kit_scan_intro') . '</p>'
    . '<div class="ck-scan" data-ck-scan data-lookup="' . rex_url::backendController(['rex-api-call' => 'consent_kit_lookup']) . '" data-labels="' . rex_escape((string) json_encode($labels)) . '">'
    . '<div class="ck-filter"><div class="ck-filter-field ck-filter-grow"><label for="ck-scan-url">' . rex_i18n::msg('consent_kit_scan_url') . '</label><input type="url" class="form-control" id="ck-scan-url" value="' . rex_escape($frontendUrl) . '"></div>'
    . '<div class="ck-filter-field ck-filter-buttons"><button type="button" class="btn btn-primary" data-ck-scan-start><i class="rex-icon fa-search" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_scan_start') . '</button></div></div>'
    . '<p class="help-block">' . rex_i18n::msg('consent_kit_scan_limits') . '</p>'
    . '<div role="status" aria-live="polite" data-ck-scan-result></div></div>';
echo $section(rex_i18n::msg('consent_kit_scan_title'), $scanner);

// Fehlende Uebersetzungen
if (WriteAssist::available() && [] !== BulkTranslator::targetLanguages()) {
    $options = '';
    foreach (BulkTranslator::targetLanguages() as $code => $name) {
        $options .= '<option value="' . rex_escape($code) . '">' . rex_escape($name . ' (' . strtoupper($code) . ')') . '</option>';
    }
    $bulk = '<p class="ck-panel-intro">' . rex_i18n::msg('consent_kit_bulk_intro', strtoupper(I18n::defaultCode())) . '</p>'
        . '<form method="post" action="' . rex_url::currentBackendPage() . '" class="ck-filter">' . $csrf->getHiddenField()
        . '<div class="ck-filter-field"><label for="ck-bulk-lang">' . rex_i18n::msg('consent_kit_bulk_target') . '</label><select class="form-control" id="ck-bulk-lang" name="target_lang">' . $options . '</select></div>'
        . '<div class="ck-filter-field ck-filter-buttons"><button type="submit" class="btn btn-primary" name="bulk_translate" value="1" data-confirm="' . rex_i18n::msg('consent_kit_bulk_confirm') . '"><i class="rex-icon fa-language" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_bulk_start') . '</button></div></form>'
        . '<p class="help-block">' . rex_i18n::msg('consent_kit_bulk_help') . '</p>';
    echo $section(rex_i18n::msg('consent_kit_bulk_title'), $bulk);
}

// Eigene Vorlagen: Export, Import, vorhandene Dateien
$presetDir = str_replace(rex_path::base(), '', PresetIo::dir()) . '/';
$services = Repository::services();
$options = '';
foreach ($services as $service) {
    $options .= '<option value="' . $service['id'] . '">' . rex_escape($service['name'] . ' (' . $service['key'] . ')') . '</option>';
}
$export = '<p class="ck-panel-intro">' . rex_i18n::rawMsg('consent_kit_presets_export_intro') . '</p>';
$export .= [] === $services
    ? '<p class="text-muted">' . rex_i18n::msg('consent_kit_presets_export_empty') . '</p>'
    : '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
        . '<div class="form-group"><label for="ck-export-ids">' . rex_i18n::msg('consent_kit_presets_export_select') . '</label>'
        . '<select class="form-control" id="ck-export-ids" name="export_ids[]" multiple size="' . min(8, count($services)) . '">' . $options . '</select>'
        . '<p class="help-block">' . rex_i18n::msg('consent_kit_presets_export_all') . '</p></div>'
        . '<button type="submit" class="btn btn-default" name="preset_export" value="1"><i class="rex-icon fa-download" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_presets_export_start') . '</button></form>';

$import = '<p class="ck-panel-intro">' . rex_i18n::msg('consent_kit_presets_import_intro') . '</p>'
    . '<form method="post" enctype="multipart/form-data" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
    . '<div class="form-group"><label for="ck-preset-json">' . rex_i18n::msg('consent_kit_presets_import_file') . '</label>'
    . '<input type="file" id="ck-preset-json" name="preset_json" accept="application/json,.json" required></div>'
    . '<div class="checkbox"><label><input type="checkbox" name="preset_overwrite" value="1"> ' . rex_i18n::msg('consent_kit_presets_import_overwrite') . '</label></div>'
    . '<button type="submit" class="btn btn-default" name="preset_import" value="1"><i class="rex-icon fa-upload" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_presets_import_start') . '</button></form>';

$files = PresetIo::files();
$list = '';
if ([] === $files) {
    $list = '<p class="text-muted">' . rex_i18n::msg('consent_kit_presets_list_empty') . '</p>';
} else {
    $rows = '';
    foreach ($files as $file) {
        $actions = '<form class="ck-inline-form" method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
            . '<input type="hidden" name="preset_file_download" value="' . rex_escape($file['file']) . '">'
            . '<button type="submit" class="btn btn-default btn-xs"><i class="rex-icon fa-download" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_presets_download') . '</button></form> '
            . '<form class="ck-inline-form" method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
            . '<input type="hidden" name="preset_file_delete" value="' . rex_escape($file['file']) . '">'
            . '<button type="submit" class="btn btn-delete btn-xs" data-confirm="' . rex_escape(rex_i18n::rawMsg('consent_kit_presets_delete_confirm', $file['file'])) . '"><i class="rex-icon fa-times" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_presets_delete') . '</button></form>';
        $rows .= '<tr><td><code>' . rex_escape($file['file']) . '</code></td><td>' . $file['count'] . '</td>'
            . '<td>' . rex_escape(implode(', ', $file['keys'])) . '</td><td class="rex-table-action">' . $actions . '</td></tr>';
    }
    $list = '<table class="table table-hover"><thead><tr><th>' . rex_i18n::msg('consent_kit_presets_col_file') . '</th>'
        . '<th>' . rex_i18n::msg('consent_kit_presets_col_count') . '</th><th>' . rex_i18n::msg('consent_kit_presets_col_keys') . '</th>'
        . '<th class="rex-table-action">&nbsp;</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}

$presets = '<p class="ck-panel-intro">' . rex_i18n::rawMsg('consent_kit_presets_intro', rex_escape($presetDir)) . '</p>'
    . '<h3>' . rex_i18n::msg('consent_kit_presets_export_title') . '</h3>' . $export
    . '<h3>' . rex_i18n::msg('consent_kit_presets_import_title') . '</h3>' . $import
    . '<h3>' . rex_i18n::msg('consent_kit_presets_list_title') . '</h3>' . $list;
echo $section(rex_i18n::msg('consent_kit_presets_title'), $presets);

// Open Cookie Database
$count = Catalog::count();
$catalog = '<p class="ck-panel-intro">' . rex_i18n::rawMsg('consent_kit_catalog_intro') . '</p>'
    . '<p>' . ($count > 0 ? rex_i18n::msg('consent_kit_catalog_count', (string) $count) : rex_i18n::msg('consent_kit_catalog_empty')) . '</p>'
    . '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
    . '<button type="submit" class="btn btn-default" name="catalog_import" value="1"><i class="rex-icon fa-cloud-download" aria-hidden="true"></i> ' . rex_i18n::msg($count > 0 ? 'consent_kit_catalog_update' : 'consent_kit_catalog_import') . '</button></form>';
echo $section(rex_i18n::msg('consent_kit_catalog_title'), $catalog);

// Uebernahme aus consent_manager
$legacy = '<p class="ck-panel-intro">' . rex_i18n::msg('consent_kit_legacy_intro') . '</p>';
if (LegacyImporter::tablesExist()) {
    $legacy .= '<form method="post" action="' . rex_url::currentBackendPage() . '" class="ck-legacy-form">' . $csrf->getHiddenField()
        . '<button type="submit" class="btn btn-default" name="legacy_tables" value="1" data-confirm="' . rex_i18n::msg('consent_kit_legacy_confirm') . '"><i class="rex-icon fa-database" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_legacy_tables') . '</button></form>';
}
$legacy .= '<form method="post" enctype="multipart/form-data" action="' . rex_url::currentBackendPage() . '" class="ck-legacy-form">' . $csrf->getHiddenField()
    . '<div class="form-group"><label for="ck-legacy-json">' . rex_i18n::msg('consent_kit_legacy_file') . '</label><input type="file" id="ck-legacy-json" name="legacy_json" accept="application/json,.json" required></div>'
    . '<button type="submit" class="btn btn-default" name="legacy_file" value="1"><i class="rex-icon fa-upload" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_legacy_upload') . '</button></form>';
echo $section(rex_i18n::msg('consent_kit_legacy_title'), $legacy);
