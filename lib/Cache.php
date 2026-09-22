<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex;
use rex_addon;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_path;
use rex_sql;

/**
 * Baut die Frontend-Konfiguration pro Domain und Sprache und legt sie als JSON
 * in var/cache ab. Geleert wird ausschliesslich ueber clear() (vom Repository
 * und den Einstellungsseiten) bzw. durch den REDAXO-Cache-Reset.
 */
final class Cache
{
    public static function clear(): void
    {
        rex_dir::delete(rex_path::addonCache('consent_kit'), false);
    }

    /**
     * @param array{id: int, host: string, privacy_article_id: int, imprint_article_id: int} $domain
     * @return array<string, mixed>
     */
    public static function config(array $domain, string $clangCode): array
    {
        $clangCode = I18n::normalize($clangCode);
        $file = rex_path::addonCache('consent_kit', 'config.' . $domain['id'] . '.' . preg_replace('~[^a-z0-9_]~', '', $clangCode) . '.json');
        $cached = rex_file::getCache($file, null);
        if (is_array($cached)) {
            return $cached;
        }
        $config = self::build($domain, $clangCode);
        rex_file::putCache($file, $config);
        return $config;
    }

    /**
     * @param array{id: int, host: string, privacy_article_id: int, imprint_article_id: int} $domain
     * @return array<string, mixed>
     */
    public static function build(array $domain, string $clangCode): array
    {
        $addon = rex_addon::get('consent_kit');
        $days = max(1, (int) $addon->getConfig('consent_days', 365));

        $services = [];
        $jsDefault = [];
        $gcmUsed = false;
        foreach (Repository::services(true) as $service) {
            if ([] !== $service['domain_ids'] && !in_array($domain['id'], $service['domain_ids'], true)) {
                continue;
            }
            $service = self::applyVariants($service, $domain, $clangCode);
            $service['js_events'] = Events::build($service['events'], PresetRepository::events($service['preset']));
            $code = self::resolveParams($service);
            if (null === $code) {
                continue;
            }
            if ('' !== $code['js_default']) {
                $jsDefault[] = $code['js_default'];
            }
            $gcmUsed = $gcmUsed || [] !== $service['gcm_signals'];

            $items = [];
            foreach ($service['items'] as $item) {
                // Laufzeit des eigenen Cookies folgt der Einstellung, nicht dem gespeicherten Wert.
                if ('consent_kit' === $service['key'] && Consent::COOKIE === $item['name']) {
                    $item['duration_value'] = 0 === $days % 365 ? intdiv($days, 365) : $days;
                    $item['duration_unit'] = 0 === $days % 365 ? 'years' : 'days';
                }
                $items[] = [
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'host' => $item['host'],
                    'duration' => Texts::duration($item['duration_value'], $item['duration_unit'], $clangCode),
                    'purpose' => I18n::pick($item['purpose'], $clangCode),
                ];
            }
            $services[$service['group_id']][] = [
                'key' => $service['key'],
                'h' => '',
                'name' => $service['name'],
                'provider' => $service['provider'],
                'privacyUrl' => $service['privacy_url'],
                'description' => I18n::pick($service['description'], $clangCode),
                'head' => $code['html_head'],
                'body' => $code['html_body'],
                'jsAccept' => $code['js_accept'],
                'jsRevoke' => $code['js_revoke'],
                'jsEvents' => $code['js_events'],
                'gcm' => $service['gcm_signals'],
                'hosts' => $service['embed_hosts'],
                'items' => $items,
            ];
        }

        $groups = [];
        foreach (Repository::groups() as $group) {
            if (!isset($services[$group['id']])) {
                continue;
            }
            foreach ($services[$group['id']] as &$entry) {
                $entry['h'] = self::serviceHash($group, $entry);
            }
            unset($entry);
            $groups[] = [
                'key' => $group['key'],
                'name' => I18n::pick($group['name'], $clangCode),
                'description' => I18n::pick($group['description'], $clangCode),
                'required' => $group['required'],
                'services' => $services[$group['id']],
            ];
        }

        $gcmMode = (string) $addon->getConfig('gcm', 'auto');
        $config = [
            'rev' => self::revision($domain['id'], $groups),
            'epoch' => self::epoch(),
            'cookie' => Consent::COOKIE,
            'days' => $days,
            'lang' => $clangCode,
            'layout' => (string) $addon->getConfig('layout', 'box'),
            'position' => (string) $addon->getConfig('position', 'bottom-left'),
            'theme' => (string) $addon->getConfig('theme', 'light'),
            'trigger' => (bool) $addon->getConfig('trigger', true),
            'triggerPosition' => (string) $addon->getConfig('trigger_position', 'bottom-left'),
            'gpc' => (string) $addon->getConfig('gpc', 'reject'),
            'reload' => (bool) $addon->getConfig('reload_on_revoke', true),
            'dismiss' => (bool) $addon->getConfig('dismissible', true),
            'gcm' => 'off' !== $gcmMode && $gcmUsed,
            'gcmOptions' => [
                'adsDataRedaction' => (bool) $addon->getConfig('gcm_ads_data_redaction', true),
                'urlPassthrough' => (bool) $addon->getConfig('gcm_url_passthrough', false),
                'waitForUpdate' => max(0, (int) $addon->getConfig('gcm_wait_for_update', 500)),
            ],
            'jsDefault' => $jsDefault,
            'texts' => Texts::all($clangCode),
            'groups' => $groups,
        ];
        // Projekte koennen die fertige Konfiguration anpassen, bevor sie gecacht wird.
        $config = rex_extension::registerPoint(new rex_extension_point('CONSENT_KIT_CONFIG', $config, ['domain' => $domain, 'clang' => $clangCode]));
        return $config;
    }

    /**
     * false, wenn dem Dienst auf irgendeiner Domain/Sprache, fuer die er gilt, noch Angaben fehlen.
     *
     * @param array<string, mixed> $service
     */
    public static function isComplete(array $service): bool
    {
        foreach (Repository::domains() as $domain) {
            if ([] !== $service['domain_ids'] && !in_array($domain['id'], $service['domain_ids'], true)) {
                continue;
            }
            foreach (array_keys(I18n::languages()) as $code) {
                if (null === self::resolveParams(self::applyVariants($service, $domain, $code))) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Legt die passenden Varianten ueber den Dienst: erst allgemein, dann nur
     * Sprache, dann nur Domain, zuletzt Domain + Sprache – die spezifischste gewinnt.
     *
     * @param array<string, mixed> $service
     * @param array{id: int, host: string, privacy_article_id: int, imprint_article_id: int} $domain
     * @return array<string, mixed>
     */
    public static function applyVariants(array $service, array $domain, string $clangCode): array
    {
        $matches = [];
        foreach ($service['variants'] ?? [] as $variant) {
            $domainFits = 0 === $variant['domain_id'] || $variant['domain_id'] === $domain['id'];
            $clangFits = '' === $variant['clang'] || $variant['clang'] === $clangCode || $variant['clang'] === substr($clangCode, 0, 2);
            if ($domainFits && $clangFits) {
                $matches[] = [(0 === $variant['domain_id'] ? 0 : 2) + ('' === $variant['clang'] ? 0 : 1), $variant];
            }
        }
        usort($matches, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        foreach ($matches as [, $variant]) {
            $service['params'] = array_merge($service['params'], $variant['params']);
            foreach (Repository::CODE_FIELDS as $field) {
                if ('' !== $variant[$field]) {
                    $service[$field] = $variant[$field];
                }
            }
        }
        $service['params'] += ['lang' => substr($clangCode, 0, 2), 'domain' => '*' === $domain['host'] ? '' : $domain['host']];
        return $service;
    }

    /**
     * Ersetzt {{param}} in allen Code-Feldern. Bleibt ein Platzhalter offen,
     * ist der Dienst unvollstaendig konfiguriert und wird nicht ausgeliefert.
     *
     * @param array<string, mixed> $service
     * @return array{html_head: string, html_body: string, js_default: string, js_accept: string, js_revoke: string, js_events: string}|null
     */
    public static function resolveParams(array $service): ?array
    {
        $replace = [];
        foreach ($service['params'] as $key => $value) {
            if ('' !== trim((string) $value)) {
                $replace['{{' . $key . '}}'] = trim((string) $value);
            }
        }
        $out = [];
        foreach (['html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke', 'js_events'] as $field) {
            $value = strtr((string) ($service[$field] ?? ''), $replace);
            if (1 === preg_match('~\{\{[a-z0-9_]+\}\}~i', $value)) {
                return null;
            }
            $out[$field] = $value;
        }
        return $out;
    }

    /** Wird vom Admin hochgezaehlt, um alle Einwilligungen neu abzufragen. */
    public static function epoch(): int
    {
        return (int) rex_addon::get('consent_kit')->getConfig('revision_bump', 0);
    }

    /**
     * Aendert sich dieser Hash, gilt eine fruehere Entscheidung zu dem Dienst
     * nicht mehr und der Dienst wird erneut abgefragt.
     *
     * @param array<string, mixed> $group
     * @param array<string, mixed> $service
     */
    private static function serviceHash(array $group, array $service): string
    {
        $items = array_map(static fn (array $i) => [$i['type'], $i['name'], $i['host']], $service['items']);
        return substr(sha1((string) json_encode([$group['key'], $group['required'], $service['key'], $service['provider'], $items])), 0, 6);
    }

    /**
     * Eine neue Revision entsteht nur, wenn sich die einwilligungsrelevanten
     * Angaben aendern (siehe serviceHash()) oder der Admin die erneute Abfrage
     * ausloest (epoch()). Texte zaehlen nicht.
     *
     * @param list<array<string, mixed>> $groups
     */
    private static function revision(int $domainId, array $groups): int
    {
        $hashes = [];
        foreach ($groups as $group) {
            foreach ($group['services'] as $service) {
                $hashes[$service['key']] = $service['h'];
            }
        }
        $hash = sha1((string) json_encode([$hashes, self::epoch()]));

        $table = rex::getTable('consent_kit_revision');
        $latest = rex_sql::factory()->getArray('SELECT id, hash FROM ' . $table . ' WHERE domain_id = ? ORDER BY id DESC LIMIT 1', [$domainId]);
        if ([] !== $latest && $latest[0]['hash'] === $hash) {
            return (int) $latest[0]['id'];
        }

        // Snapshot (ohne Code-Felder) als Nachweis, was zur Auswahl stand.
        $snapshot = array_map(static function (array $group): array {
            $group['services'] = array_map(static fn (array $s) => array_diff_key($s, array_flip(['head', 'body', 'jsAccept', 'jsRevoke'])), $group['services']);
            return $group;
        }, $groups);
        $sql = rex_sql::factory()->setTable($table);
        $sql->setValue('domain_id', $domainId);
        $sql->setValue('hash', $hash);
        $sql->setValue('snapshot', (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sql->setValue('createdate', date('Y-m-d H:i:s'));
        $sql->insert();
        return (int) $sql->getLastId();
    }
}
