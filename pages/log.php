<?php

use KLXM\ConsentKit\Log;

$addon = rex_addon::get('consent_kit');
$csrf = rex_csrf_token::factory('consent_kit');
$table = rex::getTable('consent_kit_log');
$message = '';

$filter = [
    'q' => trim(rex_request::get('q', 'string', '')),
    'action' => rex_request::get('action', 'string', ''),
    'from' => rex_request::get('from', 'string', ''),
    'to' => rex_request::get('to', 'string', ''),
];
// rex_list kennt keine gebundenen Parameter, deshalb werden die Werte escaped eingesetzt.
$sql = rex_sql::factory();
$where = [];
if ('' !== $filter['q']) {
    $where[] = 'consent_id LIKE ' . $sql->escape($sql->escapeLikeWildcards($filter['q']) . '%');
}
if (in_array($filter['action'], Log::ACTIONS, true)) {
    $where[] = 'action = ' . $sql->escape($filter['action']);
} else {
    $filter['action'] = '';
}
foreach (['from' => ['>=', ' 00:00:00'], 'to' => ['<=', ' 23:59:59']] as $key => [$operator, $time]) {
    if (1 === preg_match('~^\d{4}-\d{2}-\d{2}$~', $filter[$key])) {
        $where[] = 'createdate ' . $operator . ' ' . $sql->escape($filter[$key] . $time);
    } else {
        $filter[$key] = '';
    }
}
$whereSql = [] === $where ? '' : ' WHERE ' . implode(' AND ', $where);

// Revision: was stand damals zur Auswahl?
$revisionId = rex_request::get('revision', 'int', 0);
if ($revisionId > 0) {
    $rows = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('consent_kit_revision') . ' WHERE id = ?', [$revisionId]);
    $body = rex_view::error(rex_i18n::msg('consent_kit_not_found'));
    if ([] !== $rows) {
        $body = '';
        foreach ((array) json_decode((string) $rows[0]['snapshot'], true) as $group) {
            $body .= '<h3>' . rex_escape((string) $group['name']) . ($group['required'] ? ' <span class="label label-default">' . rex_i18n::msg('consent_kit_required') . '</span>' : '') . '</h3><ul>';
            foreach ((array) $group['services'] as $service) {
                $items = array_map(static fn (array $i) => $i['name'] . ' (' . $i['duration'] . ')', (array) $service['items']);
                $body .= '<li><strong>' . rex_escape((string) $service['name']) . '</strong> <code>' . rex_escape((string) $service['key']) . '</code>'
                    . ('' !== (string) $service['provider'] ? ' – ' . rex_escape((string) $service['provider']) : '')
                    . ([] !== $items ? '<br><span class="text-muted">' . rex_escape(implode(', ', $items)) . '</span>' : '') . '</li>';
            }
            $body .= '</ul>';
        }
    }
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('consent_kit_revision_title', (string) $revisionId, [] !== $rows ? rex_formatter::intlDateTime((string) $rows[0]['createdate']) : ''), false);
    $fragment->setVar('options', '<a class="btn btn-default btn-xs" href="' . rex_url::currentBackendPage() . '"><i class="rex-icon fa-chevron-left" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_back') . '</a>', false);
    $fragment->setVar('body', $body, false);
    echo $fragment->parse('core/page/section.php');
    return;
}

if ('csv' === rex_request::get('func', 'string', '')) {
    rex_response::cleanOutputBuffers();
    $out = fopen('php://temp', 'r+');
    if (false !== $out) {
        fputcsv($out, ['createdate', 'consent_id', 'host', 'revision_id', 'action', 'accepted', 'rejected', 'gpc', 'clang'], ';', '"', '');
        foreach (rex_sql::factory()->getArray('SELECT createdate, consent_id, host, revision_id, action, accepted, rejected, gpc, clang FROM ' . $table . $whereSql . ' ORDER BY id DESC LIMIT 100000') as $row) {
            fputcsv($out, array_map('strval', $row), ';', '"', '');
        }
        rewind($out);
        rex_response::setHeader('Content-Disposition', 'attachment; filename="consent-log-' . date('Y-m-d') . '.csv"');
        rex_response::sendContent("\xEF\xBB\xBF" . stream_get_contents($out), 'text/csv');
    }
    exit;
}

if ('post' === rex_request::requestMethod() && rex_request::post('purge', 'bool', false)) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (rex::getUser()?->isAdmin()) {
        $message = rex_view::success(rex_i18n::msg('consent_kit_log_purged', (string) Log::purge()));
    }
}

// Kennzahlen
$stats = Log::stats(30);
$percent = static fn (int $value): string => $stats['total'] > 0 ? (string) round($value / $stats['total'] * 100) . ' %' : '–';
$cards = '';
foreach ([
    [rex_i18n::msg('consent_kit_stat_total'), (string) $stats['total'], rex_i18n::msg('consent_kit_stat_total_hint')],
    [rex_i18n::msg('consent_kit_action_accept_all'), $percent($stats['actions']['accept_all']), (string) $stats['actions']['accept_all']],
    [rex_i18n::msg('consent_kit_action_reject_all'), $percent($stats['actions']['reject_all'] + $stats['actions']['gpc']), (string) ($stats['actions']['reject_all'] + $stats['actions']['gpc'])],
    [rex_i18n::msg('consent_kit_action_custom'), $percent($stats['actions']['custom'] + $stats['actions']['embed']), (string) ($stats['actions']['custom'] + $stats['actions']['embed'])],
    [rex_i18n::msg('consent_kit_stat_gpc'), $percent($stats['gpc']), (string) $stats['gpc']],
] as [$label, $value, $hint]) {
    $cards .= '<div class="ck-stat"><dt>' . $label . '</dt><dd>' . $value . '<small>' . rex_escape($hint) . '</small></dd></div>';
}
$bars = '';
foreach ($stats['services'] as $key => $count) {
    $share = $stats['total'] > 0 ? (int) round($count / $stats['total'] * 100) : 0;
    $bars .= '<tr><th scope="row"><code>' . rex_escape($key) . '</code></th><td class="ck-bar-cell"><span class="ck-bar" style="width:' . $share . '%" aria-hidden="true"></span></td><td class="ck-bar-value">' . $share . ' % <small class="text-muted">(' . $count . ')</small></td></tr>';
}
$statsBody = '<dl class="ck-stats">' . $cards . '</dl>'
    . ('' !== $bars ? '<table class="ck-bars"><caption>' . rex_i18n::msg('consent_kit_stat_services') . '</caption><tbody>' . $bars . '</tbody></table>' : '');

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('consent_kit_stats_title'), false);
$fragment->setVar('body', $statsBody, false);
echo $message . $fragment->parse('core/page/section.php');

// Filter
$actionOptions = '<option value="">' . rex_i18n::msg('consent_kit_all') . '</option>';
foreach (Log::ACTIONS as $action) {
    $actionOptions .= '<option value="' . $action . '"' . ($action === $filter['action'] ? ' selected' : '') . '>' . rex_i18n::msg('consent_kit_action_' . $action) . '</option>';
}
$filterForm = '<form method="get" action="' . rex_url::backendController() . '" class="ck-filter" role="search" aria-label="' . rex_i18n::msg('consent_kit_log_filter') . '">'
    . '<input type="hidden" name="page" value="consent_kit/log">'
    . '<div class="ck-filter-field ck-filter-grow"><label for="ck-log-q">' . rex_i18n::msg('consent_kit_log_consent_id') . '</label><input type="search" class="form-control" id="ck-log-q" name="q" value="' . rex_escape($filter['q']) . '" placeholder="xxxxxxxx-xxxx-…" spellcheck="false" autocomplete="off"></div>'
    . '<div class="ck-filter-field"><label for="ck-log-action">' . rex_i18n::msg('consent_kit_log_action') . '</label><select class="form-control" id="ck-log-action" name="action">' . $actionOptions . '</select></div>'
    . '<div class="ck-filter-field"><label for="ck-log-from">' . rex_i18n::msg('consent_kit_log_from') . '</label><input type="date" class="form-control" id="ck-log-from" name="from" value="' . rex_escape($filter['from']) . '"></div>'
    . '<div class="ck-filter-field"><label for="ck-log-to">' . rex_i18n::msg('consent_kit_log_to') . '</label><input type="date" class="form-control" id="ck-log-to" name="to" value="' . rex_escape($filter['to']) . '"></div>'
    . '<div class="ck-filter-field ck-filter-buttons"><button type="submit" class="btn btn-primary">' . rex_i18n::msg('consent_kit_log_apply') . '</button> '
    . '<a class="btn btn-default" href="' . rex_url::currentBackendPage() . '">' . rex_i18n::msg('consent_kit_log_reset') . '</a></div></form>';

// Liste
$list = rex_list::factory('SELECT id, createdate, consent_id, host, action, accepted, rejected, gpc, clang, revision_id FROM ' . $table . $whereSql . ' ORDER BY id DESC', 50, 'consent_kit_log');
$list->addParam('q', $filter['q']);
$list->addParam('action', $filter['action']);
$list->addParam('from', $filter['from']);
$list->addParam('to', $filter['to']);
$list->addTableAttribute('class', 'table-hover ck-log');
$list->removeColumn('id');
$list->removeColumn('rejected');
$list->removeColumn('clang');
$list->setNoRowsMessage(rex_i18n::msg('consent_kit_log_empty'));
foreach (['createdate', 'consent_id', 'host', 'action', 'accepted', 'gpc', 'revision_id'] as $column) {
    $list->setColumnLabel($column, rex_i18n::msg('consent_kit_log_col_' . $column));
}
$list->setColumnFormat('createdate', 'custom', static fn (array $p) => rex_escape(rex_formatter::intlDateTime((string) $p['list']->getValue('createdate'), [IntlDateFormatter::MEDIUM, IntlDateFormatter::MEDIUM])));
$list->setColumnFormat('consent_id', 'custom', static fn (array $p) => '<code class="ck-uuid">' . rex_escape((string) $p['list']->getValue('consent_id')) . '</code>');
$list->setColumnFormat('action', 'custom', static function (array $p) {
    $action = (string) $p['list']->getValue('action');
    return '<span class="ck-action ck-action-' . rex_escape($action) . '">' . rex_i18n::msg('consent_kit_action_' . (in_array($action, Log::ACTIONS, true) ? $action : 'custom')) . '</span>';
});
$list->setColumnFormat('accepted', 'custom', static function (array $p) {
    $accepted = array_filter(explode(',', (string) $p['list']->getValue('accepted')));
    $rejected = array_filter(explode(',', (string) $p['list']->getValue('rejected')));
    $out = '';
    foreach ($accepted as $key) {
        $out .= '<span class="ck-chip ck-chip-on"><i class="rex-icon fa-check" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_log_accepted') . ': </span>' . rex_escape($key) . '</span> ';
    }
    foreach ($rejected as $key) {
        $out .= '<span class="ck-chip ck-chip-off"><i class="rex-icon fa-times" aria-hidden="true"></i><span class="sr-only">' . rex_i18n::msg('consent_kit_log_rejected') . ': </span>' . rex_escape($key) . '</span> ';
    }
    return $out;
});
$list->setColumnFormat('gpc', 'custom', static fn (array $p) => 1 === (int) $p['list']->getValue('gpc') ? '<span class="label label-info">GPC</span>' : '');
$list->setColumnFormat('revision_id', 'custom', static fn (array $p) => '<a href="' . rex_url::currentBackendPage(['revision' => (int) $p['list']->getValue('revision_id')]) . '">#' . (int) $p['list']->getValue('revision_id') . '</a>');

$options = '<a class="btn btn-default btn-xs" href="' . rex_url::currentBackendPage(array_filter($filter) + ['func' => 'csv']) . '"><i class="rex-icon fa-download" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_log_csv') . '</a>';
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('consent_kit_log'), false);
$fragment->setVar('options', $options, false);
$fragment->setVar('content', '<div class="ck-filter-wrap">' . $filterForm . '</div>' . $list->get(), false);
echo $fragment->parse('core/page/section.php');

if (rex::getUser()?->isAdmin()) {
    $days = (int) $addon->getConfig('log_days', 1095);
    $body = '<p>' . rex_i18n::msg('consent_kit_log_retention', (string) $days) . ' ' . rex_i18n::msg('consent_kit_log_privacy') . '</p>'
        . '<form method="post" action="' . rex_url::currentBackendPage() . '">' . $csrf->getHiddenField()
        . '<button type="submit" class="btn btn-default" name="purge" value="1" data-confirm="' . rex_i18n::msg('consent_kit_log_purge_confirm') . '"' . ($days > 0 ? '' : ' disabled') . '>' . rex_i18n::msg('consent_kit_log_purge') . '</button></form>';
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('consent_kit_log_retention_title'), false);
    $fragment->setVar('body', $body, false);
    echo $fragment->parse('core/page/section.php');
}
