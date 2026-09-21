<?php

namespace KLXM\ConsentKit;

use rex;
use rex_socket;
use rex_sql;
use RuntimeException;

/**
 * Optionaler Nachschlage-Katalog: Open Cookie Database (Apache-2.0),
 * https://github.com/jkwakman/Open-Cookie-Database. Wird nicht mitgeliefert,
 * sondern auf Knopfdruck geladen.
 */
final class Catalog
{
    public const SOURCE = 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/master/open-cookie-database.csv';

    public static function count(): int
    {
        $rows = rex_sql::factory()->getArray('SELECT COUNT(*) AS c FROM ' . rex::getTable('consent_kit_catalog'));
        return (int) $rows[0]['c'];
    }

    public static function import(): int
    {
        $response = rex_socket::factoryUrl(self::SOURCE)->setTimeout(20)->doGet();
        if (!$response->isOk()) {
            throw new RuntimeException('HTTP ' . $response->getStatusCode());
        }
        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new RuntimeException('temp stream');
        }
        fwrite($stream, $response->getBody());
        rewind($stream);

        $header = fgetcsv($stream, null, ',', '"', '');
        if (!is_array($header) || count($header) < 10) {
            throw new RuntimeException('Unexpected CSV header');
        }
        $table = rex::getTable('consent_kit_catalog');
        rex_sql::factory()->setQuery('TRUNCATE TABLE ' . $table);
        $count = 0;
        // Spalten: ID, Platform, Category, Cookie / Data Key name, Domain, Description, Retention period, Data Controller, Privacy link, Wildcard match
        while (false !== ($row = fgetcsv($stream, null, ',', '"', ''))) {
            if (count($row) < 10 || '' === trim((string) $row[3])) {
                continue;
            }
            rex_sql::factory()
                ->setTable($table)
                ->setValue('ocd_id', substr((string) $row[0], 0, 64))
                ->setValue('platform', substr((string) $row[1], 0, 191))
                ->setValue('category', substr((string) $row[2], 0, 64))
                ->setValue('name', substr(trim((string) $row[3]), 0, 191))
                ->setValue('host', substr((string) $row[4], 0, 191))
                ->setValue('description', (string) $row[5])
                ->setValue('retention', substr((string) $row[6], 0, 191))
                ->setValue('controller', substr((string) $row[7], 0, 191))
                ->setValue('wildcard', '1' === trim((string) $row[9]) ? 1 : 0)
                ->insert();
            ++$count;
        }
        fclose($stream);
        return $count;
    }

    /**
     * Exakter Treffer oder Wildcard-Praefix (OCD fuehrt "_ga_" mit wildcard=1).
     *
     * @return array<string, mixed>|null
     */
    public static function lookup(string $name): ?array
    {
        $table = rex::getTable('consent_kit_catalog');
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . $table . ' WHERE name = ? AND wildcard = 0 LIMIT 1', [$name]);
        if ([] === $rows) {
            $rows = rex_sql::factory()->getArray(
                'SELECT * FROM ' . $table . ' WHERE wildcard = 1 AND ? LIKE CONCAT(REPLACE(REPLACE(name, "_", "\\\\_"), "%", "\\\\%"), "%") ORDER BY LENGTH(name) DESC LIMIT 1',
                [$name],
            );
        }
        return $rows[0] ?? null;
    }
}
