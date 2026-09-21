<?php

namespace KLXM\ConsentKit;

use rex;
use rex_addon;
use rex_sql;
use rex_yrewrite;

/**
 * Einziger Schreib-/Lesezugriff auf Gruppen, Dienste, Eintraege und Domains.
 * Jede Schreiboperation leert den Konfigurations-Cache (Cache::clear()).
 */
final class Repository
{
    public const CODE_FIELDS = ['html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke'];
    public const ITEM_TYPES = ['cookie', 'local_storage', 'session_storage', 'indexed_db'];
    public const DURATION_UNITS = ['session', 'minutes', 'hours', 'days', 'months', 'years', 'persistent'];
    public const GCM_SIGNALS = [
        'ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage',
        'functionality_storage', 'personalization_storage', 'security_storage',
    ];

    /** @return list<array<string, mixed>> */
    public static function groups(): array
    {
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('consent_kit_group') . ' ORDER BY prio, id');
        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'key' => (string) $row['key'],
                'prio' => (int) $row['prio'],
                'required' => 1 === (int) $row['required'],
                'name' => I18n::decode((string) $row['name']),
                'description' => I18n::decode((string) $row['description']),
            ];
        }, $rows);
    }

    /** @return array<string, mixed>|null */
    public static function group(int $id): ?array
    {
        foreach (self::groups() as $group) {
            if ($group['id'] === $id) {
                return $group;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $data */
    public static function saveGroup(int $id, array $data): int
    {
        $sql = rex_sql::factory()->setTable(rex::getTable('consent_kit_group'));
        $sql->setValue('key', (string) $data['key']);
        $sql->setValue('required', !empty($data['required']) ? 1 : 0);
        $sql->setValue('name', I18n::encode((array) $data['name']));
        $sql->setValue('description', I18n::encode((array) $data['description']));
        if ($id > 0) {
            $sql->setWhere(['id' => $id])->update();
        } else {
            $sql->setValue('prio', self::nextPrio('consent_kit_group'));
            $sql->insert();
            $id = (int) $sql->getLastId();
        }
        Cache::clear();
        return $id;
    }

    public static function deleteGroup(int $id): bool
    {
        $count = rex_sql::factory()->getArray(
            'SELECT COUNT(*) AS c FROM ' . rex::getTable('consent_kit_service') . ' WHERE group_id = ?', [$id],
        );
        if ((int) $count[0]['c'] > 0) {
            return false;
        }
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_group') . ' WHERE id = ?', [$id]);
        Cache::clear();
        return true;
    }

    /** @return list<array<string, mixed>> */
    public static function services(bool $onlyActive = false): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . rex::getTable('consent_kit_service')
            . ($onlyActive ? ' WHERE status = 1' : '') . ' ORDER BY prio, id',
        );
        $items = [];
        foreach (rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('consent_kit_item') . ' ORDER BY prio, id') as $item) {
            $items[(int) $item['service_id']][] = [
                'id' => (int) $item['id'],
                'type' => (string) $item['type'],
                'name' => (string) $item['name'],
                'host' => (string) $item['host'],
                'duration_value' => (int) $item['duration_value'],
                'duration_unit' => (string) $item['duration_unit'],
                'purpose' => I18n::decode((string) $item['purpose']),
            ];
        }
        $variants = [];
        foreach (rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('consent_kit_variant') . ' ORDER BY id') as $variant) {
            $variantParams = json_decode((string) $variant['params'], true);
            $entry = [
                'domain_id' => (int) $variant['domain_id'],
                'clang' => (string) $variant['clang'],
                'params' => is_array($variantParams) ? array_map('strval', $variantParams) : [],
            ];
            foreach (self::CODE_FIELDS as $field) {
                $entry[$field] = (string) $variant[$field];
            }
            $variants[(int) $variant['service_id']][] = $entry;
        }
        return array_map(static function (array $row) use ($items, $variants): array {
            $id = (int) $row['id'];
            $params = json_decode((string) $row['params'], true);
            return [
                'id' => $id,
                'key' => (string) $row['key'],
                'group_id' => (int) $row['group_id'],
                'prio' => (int) $row['prio'],
                'status' => 1 === (int) $row['status'],
                'name' => (string) $row['name'],
                'provider' => (string) $row['provider'],
                'privacy_url' => (string) $row['privacy_url'],
                'description' => I18n::decode((string) $row['description']),
                'params' => is_array($params) ? array_map('strval', $params) : [],
                'html_head' => (string) $row['html_head'],
                'html_body' => (string) $row['html_body'],
                'js_default' => (string) $row['js_default'],
                'js_accept' => (string) $row['js_accept'],
                'js_revoke' => (string) $row['js_revoke'],
                'gcm_signals' => self::splitList((string) $row['gcm_signals']),
                'embed_hosts' => self::splitList((string) $row['embed_hosts']),
                'domain_ids' => array_map('intval', self::splitList((string) $row['domain_ids'])),
                'preset' => (string) $row['preset'],
                'items' => $items[$id] ?? [],
                'variants' => $variants[$id] ?? [],
                'updatedate' => (string) $row['updatedate'],
            ];
        }, $rows);
    }

    /** @return array<string, mixed>|null */
    public static function service(int $id): ?array
    {
        foreach (self::services() as $service) {
            if ($service['id'] === $id) {
                return $service;
            }
        }
        return null;
    }

    public static function serviceKeyExists(string $key, int $exceptId = 0): bool
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT id FROM ' . rex::getTable('consent_kit_service') . ' WHERE `key` = ? AND id <> ?', [$key, $exceptId],
        );
        return [] !== $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>>|null $variants null = vorhandene Varianten behalten
     */
    public static function saveService(int $id, array $data, array $items, ?array $variants = null): int
    {
        $user = rex::getUser()?->getLogin() ?? 'consent_kit';
        $sql = rex_sql::factory()->setTable(rex::getTable('consent_kit_service'));
        $sql->setValue('key', (string) $data['key']);
        $sql->setValue('group_id', (int) $data['group_id']);
        $sql->setValue('status', !empty($data['status']) ? 1 : 0);
        $sql->setValue('name', (string) $data['name']);
        $sql->setValue('provider', (string) ($data['provider'] ?? ''));
        $sql->setValue('privacy_url', (string) ($data['privacy_url'] ?? ''));
        $sql->setValue('description', I18n::encode((array) ($data['description'] ?? [])));
        $sql->setValue('params', (string) json_encode((object) ($data['params'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach (['html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke'] as $field) {
            $sql->setValue($field, trim((string) ($data[$field] ?? '')));
        }
        $signals = array_values(array_intersect(self::GCM_SIGNALS, (array) ($data['gcm_signals'] ?? [])));
        $sql->setValue('gcm_signals', implode(',', $signals));
        $sql->setValue('embed_hosts', implode(',', self::normalizeHosts((array) ($data['embed_hosts'] ?? []))));
        $sql->setValue('domain_ids', implode(',', array_filter(array_map('intval', (array) ($data['domain_ids'] ?? [])))));
        $sql->setValue('preset', (string) ($data['preset'] ?? ''));
        $sql->addGlobalUpdateFields($user);
        if ($id > 0) {
            $sql->setWhere(['id' => $id])->update();
        } else {
            $sql->setValue('prio', self::nextPrio('consent_kit_service'));
            $sql->addGlobalCreateFields($user);
            $sql->insert();
            $id = (int) $sql->getLastId();
        }

        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_item') . ' WHERE service_id = ?', [$id]);
        $prio = 0;
        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ('' === $name) {
                continue;
            }
            $type = in_array($item['type'] ?? '', self::ITEM_TYPES, true) ? (string) $item['type'] : 'cookie';
            $unit = in_array($item['duration_unit'] ?? '', self::DURATION_UNITS, true) ? (string) $item['duration_unit'] : 'session';
            $value = in_array($unit, ['session', 'persistent'], true) ? 0 : max(0, (int) ($item['duration_value'] ?? 0));
            rex_sql::factory()
                ->setTable(rex::getTable('consent_kit_item'))
                ->setValue('service_id', $id)
                ->setValue('prio', ++$prio)
                ->setValue('type', $type)
                ->setValue('name', $name)
                ->setValue('host', trim((string) ($item['host'] ?? '')))
                ->setValue('duration_value', $value)
                ->setValue('duration_unit', $unit)
                ->setValue('purpose', I18n::encode((array) ($item['purpose'] ?? [])))
                ->insert();
        }
        if (null !== $variants) {
            rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_variant') . ' WHERE service_id = ?', [$id]);
            foreach ($variants as $variant) {
                $params = array_filter(array_map('trim', array_map('strval', (array) ($variant['params'] ?? []))), static fn (string $v) => '' !== $v);
                $code = [];
                foreach (self::CODE_FIELDS as $field) {
                    $code[$field] = trim((string) ($variant[$field] ?? ''));
                }
                // Eine Variante ohne jede Abweichung ist keine.
                if ([] === $params && '' === implode('', $code)) {
                    continue;
                }
                $sql = rex_sql::factory()
                    ->setTable(rex::getTable('consent_kit_variant'))
                    ->setValue('service_id', $id)
                    ->setValue('domain_id', max(0, (int) ($variant['domain_id'] ?? 0)))
                    ->setValue('clang', substr(I18n::normalize((string) ($variant['clang'] ?? '')), 0, 10))
                    ->setValue('params', (string) json_encode((object) $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                foreach ($code as $field => $value) {
                    $sql->setValue($field, $value);
                }
                $sql->insert();
            }
        }
        Cache::clear();
        return $id;
    }

    /**
     * Schaltet einen Dienst fuer eine Domain um. Leere domain_ids bedeuten
     * "alle Domains"; die letzte Domain laesst sich nicht abwaehlen (dafuer gibt es den Status).
     */
    public static function toggleServiceDomain(int $id, int $domainId): bool
    {
        $service = self::service($id);
        $all = array_column(array_filter(self::domains(), static fn (array $d) => '*' !== $d['host']), 'id');
        if (null === $service || !in_array($domainId, $all, true)) {
            return false;
        }
        $current = [] === $service['domain_ids'] ? $all : array_values(array_intersect($all, $service['domain_ids']));
        $next = in_array($domainId, $current, true) ? array_values(array_diff($current, [$domainId])) : array_merge($current, [$domainId]);
        if ([] === $next) {
            return false;
        }
        sort($next);
        rex_sql::factory()->setQuery(
            'UPDATE ' . rex::getTable('consent_kit_service') . ' SET domain_ids = ? WHERE id = ?',
            [count($next) === count($all) ? '' : implode(',', $next), $id],
        );
        Cache::clear();
        return true;
    }

    public static function setServiceStatus(int $id, bool $status): void
    {
        rex_sql::factory()->setQuery(
            'UPDATE ' . rex::getTable('consent_kit_service') . ' SET status = ? WHERE id = ?', [$status ? 1 : 0, $id],
        );
        Cache::clear();
    }

    public static function deleteService(int $id): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_item') . ' WHERE service_id = ?', [$id]);
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_variant') . ' WHERE service_id = ?', [$id]);
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_service') . ' WHERE id = ?', [$id]);
        Cache::clear();
    }

    /** Tauscht die Prio mit dem Nachbarn; bei Diensten nur innerhalb der Gruppe. */
    public static function move(string $type, int $id, int $direction): void
    {
        $table = rex::getTable('group' === $type ? 'consent_kit_group' : 'consent_kit_service');
        $where = '';
        $params = [];
        if ('service' === $type) {
            $row = rex_sql::factory()->getArray('SELECT group_id FROM ' . $table . ' WHERE id = ?', [$id]);
            if ([] === $row) {
                return;
            }
            $where = ' WHERE group_id = ?';
            $params = [(int) $row[0]['group_id']];
        }
        $ids = array_map(
            static fn (array $r) => (int) $r['id'],
            rex_sql::factory()->getArray('SELECT id FROM ' . $table . $where . ' ORDER BY prio, id', $params),
        );
        $index = array_search($id, $ids, true);
        $target = false === $index ? -1 : $index + $direction;
        if (false === $index || $target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        foreach ($ids as $prio => $rowId) {
            rex_sql::factory()->setQuery('UPDATE ' . $table . ' SET prio = ? WHERE id = ?', [$prio + 1, $rowId]);
        }
        Cache::clear();
    }

    /** @return list<array{id: int, host: string, privacy_article_id: int, imprint_article_id: int}> */
    public static function domains(): array
    {
        $rows = rex_sql::factory()->getArray('SELECT * FROM ' . rex::getTable('consent_kit_domain') . " ORDER BY host = '*' DESC, host");
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'host' => (string) $row['host'],
            'privacy_article_id' => (int) $row['privacy_article_id'],
            'imprint_article_id' => (int) $row['imprint_article_id'],
        ], $rows);
    }

    /**
     * Legt fuer jede YRewrite-Domain einen Eintrag an, damit Dienste und
     * Rechtstexte ohne Handarbeit pro Domain zugeordnet werden koennen.
     */
    public static function syncYrewriteDomains(): void
    {
        if (!rex_addon::get('yrewrite')->isAvailable()) {
            return;
        }
        $known = array_column(self::domains(), 'host');
        foreach (rex_yrewrite::getDomains() as $domain) {
            $host = self::normalizeHost($domain->getHost());
            if ('' === $host || 'default' === $domain->getName() || in_array($host, $known, true)) {
                continue;
            }
            rex_sql::factory()->setTable(rex::getTable('consent_kit_domain'))->setValue('host', $host)->insert();
            $known[] = $host;
        }
    }

    public static function saveDomain(int $id, string $host, int $privacyArticleId, int $imprintArticleId): int
    {
        $sql = rex_sql::factory()->setTable(rex::getTable('consent_kit_domain'));
        $sql->setValue('host', $host);
        $sql->setValue('privacy_article_id', $privacyArticleId);
        $sql->setValue('imprint_article_id', $imprintArticleId);
        if ($id > 0) {
            $sql->setWhere(['id' => $id])->update();
        } else {
            $sql->insert();
            $id = (int) $sql->getLastId();
        }
        Cache::clear();
        return $id;
    }

    public static function deleteDomain(int $id): void
    {
        rex_sql::factory()->setQuery('DELETE FROM ' . rex::getTable('consent_kit_domain') . " WHERE id = ? AND host <> '*'", [$id]);
        Cache::clear();
    }

    /** "https://www.Example.org:8443/pfad" -> "example.org". */
    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ('*' === $host) {
            return '*';
        }
        $host = (string) preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $host);
        $host = (string) preg_replace('~[/?#].*$~', '', $host);
        $host = (string) preg_replace('~:\d+$~', '', $host);
        return (string) preg_replace('~^www\.~', '', $host);
    }

    /**
     * @param array<mixed> $hosts
     * @return list<string>
     */
    public static function normalizeHosts(array $hosts): array
    {
        $out = [];
        foreach ($hosts as $host) {
            $host = self::normalizeHost((string) $host);
            if ('' !== $host && '*' !== $host) {
                $out[$host] = $host;
            }
        }
        return array_values($out);
    }

    /** @return list<string> */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v) => '' !== $v));
    }

    /** @param non-empty-string $table */
    private static function nextPrio(string $table): int
    {
        $rows = rex_sql::factory()->getArray('SELECT MAX(prio) AS p FROM ' . rex::getTable($table));
        return (int) ($rows[0]['p'] ?? 0) + 1;
    }
}
