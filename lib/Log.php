<?php

namespace KLXM\ConsentKit;

use rex;
use rex_addon;
use rex_sql;

final class Log
{
    public const ACTIONS = ['accept_all', 'reject_all', 'custom', 'gpc', 'embed'];

    /** Loescht Eintraege, die aelter als die Aufbewahrungsfrist sind. */
    public static function purge(?int $days = null): int
    {
        $days ??= (int) rex_addon::get('consent_kit')->getConfig('log_days', 1095);
        if ($days <= 0) {
            return 0;
        }
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('consent_kit_log') . ' WHERE createdate < ?',
            [date('Y-m-d H:i:s', time() - $days * 86400)],
        );
        return (int) $sql->getRows();
    }

    /**
     * Kennzahlen der letzten $days Tage. Pro Einwilligungs-ID zaehlt nur die letzte Entscheidung.
     *
     * @return array{total: int, actions: array<string, int>, services: array<string, int>, gpc: int}
     */
    public static function stats(int $days = 30): array
    {
        $table = rex::getTable('consent_kit_log');
        $rows = rex_sql::factory()->getArray(
            'SELECT l.action, l.accepted, l.gpc FROM ' . $table . ' l
             INNER JOIN (SELECT MAX(id) AS id FROM ' . $table . ' WHERE createdate >= ? GROUP BY consent_id) latest ON latest.id = l.id',
            [date('Y-m-d H:i:s', time() - $days * 86400)],
        );
        $stats = ['total' => count($rows), 'actions' => array_fill_keys(self::ACTIONS, 0), 'services' => [], 'gpc' => 0];
        foreach ($rows as $row) {
            $action = (string) $row['action'];
            $stats['actions'][$action] = ($stats['actions'][$action] ?? 0) + 1;
            $stats['gpc'] += (int) $row['gpc'];
            foreach (array_filter(explode(',', (string) $row['accepted'])) as $key) {
                $stats['services'][$key] = ($stats['services'][$key] ?? 0) + 1;
            }
        }
        arsort($stats['services']);
        return $stats;
    }
}
