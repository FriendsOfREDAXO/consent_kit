<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex;
use rex_clang;
use rex_sql;
use rex_sql_table;
use rex_string;
use Throwable;

/**
 * Uebernimmt Gruppen, Dienste und Domains aus dem AddOn consent_manager –
 * direkt aus dessen Tabellen oder aus einem seiner JSON-Exporte.
 * Vorhandene Schluessel werden nie ueberschrieben.
 */
final class LegacyImporter
{
    /** @var list<string> */
    private array $notes = [];
    private int $groups = 0;
    private int $services = 0;
    private int $domains = 0;

    public static function tablesExist(): bool
    {
        return rex_sql_table::get(rex::getTable('consent_manager_cookie'))->exists()
            && rex_sql_table::get(rex::getTable('consent_manager_cookiegroup'))->exists();
    }

    /** @return array{groups: int, services: int, domains: int, notes: list<string>} */
    public function fromTables(): array
    {
        $sql = rex_sql::factory();
        $data = [
            'cookiegroups' => $sql->getArray('SELECT * FROM ' . rex::getTable('consent_manager_cookiegroup') . ' ORDER BY prio, id'),
            'cookies' => $sql->getArray('SELECT * FROM ' . rex::getTable('consent_manager_cookie') . ' ORDER BY id'),
            'domains' => rex_sql_table::get(rex::getTable('consent_manager_domain'))->exists()
                ? $sql->getArray('SELECT * FROM ' . rex::getTable('consent_manager_domain'))
                : [],
        ];
        return $this->fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{groups: int, services: int, domains: int, notes: list<string>}
     */
    public function fromArray(array $data): array
    {
        $clangCodes = [];
        foreach (rex_clang::getAll() as $clang) {
            $clangCodes[$clang->getId()] = I18n::normalize($clang->getCode());
        }
        $code = static fn (mixed $clangId): string => $clangCodes[(int) $clangId] ?? I18n::defaultCode();

        // Dienste sprachuebergreifend nach uid zusammenfuehren.
        /** @var array<string, array{name: string, provider: string, privacy_url: string, description: array<string, string>, html_head: string, js_revoke: string, items: array<string, array<string, mixed>>}> $services */
        $services = [];
        foreach ((array) ($data['cookies'] ?? []) as $row) {
            $uid = $this->key((string) ($row['uid'] ?? ''));
            if ('' === $uid) {
                continue;
            }
            $lang = $code($row['clang_id'] ?? 0);
            $services[$uid] ??= ['name' => '', 'provider' => '', 'privacy_url' => '', 'description' => [], 'html_head' => '', 'js_revoke' => '', 'items' => []];
            $service = &$services[$uid];
            $service['name'] = $service['name'] ?: trim((string) ($row['service_name'] ?? '')) ?: $uid;
            $service['provider'] = $service['provider'] ?: trim((string) ($row['provider'] ?? ''));
            $url = trim((string) ($row['provider_link_privacy'] ?? ''));
            if ('' === $service['privacy_url'] && 1 === preg_match('~^https?://~i', $url)) {
                $service['privacy_url'] = $url;
            }
            $service['html_head'] = $service['html_head'] ?: trim((string) ($row['script'] ?? ''));
            if ('' === $service['js_revoke'] && '' !== trim((string) ($row['script_unselect'] ?? ''))) {
                $js = $this->inlineScript((string) $row['script_unselect']);
                if (null === $js) {
                    $this->notes[] = $uid . ': script_unselect enthält mehr als ein Inline-Script und wurde nicht übernommen.';
                } else {
                    $service['js_revoke'] = $js;
                }
            }
            try {
                $definition = rex_string::yamlDecode((string) ($row['definition'] ?? ''));
            } catch (Throwable) {
                $definition = [];
                $this->notes[] = $uid . ': Cookie-Definition (YAML) nicht lesbar.';
            }
            foreach ($definition as $entry) {
                $name = trim((string) ($entry['name'] ?? ''));
                if ('' === $name) {
                    continue;
                }
                $service['items'][$name] ??= ['type' => 'cookie', 'name' => $name, 'host' => '', 'duration_value' => 0, 'duration_unit' => 'session', 'purpose' => [], 'parsed' => false];
                $item = &$service['items'][$name];
                $purpose = trim((string) ($entry['desc'] ?? ''));
                $time = trim((string) ($entry['time'] ?? ''));
                if (!$item['parsed']) {
                    $duration = $this->duration($time);
                    if (null !== $duration) {
                        [$item['duration_value'], $item['duration_unit']] = $duration;
                        $item['parsed'] = true;
                    }
                }
                if ('' !== $purpose || '' !== $time) {
                    // Freitext-Laufzeiten lassen sich nicht sicher umrechnen; das Original bleibt sichtbar.
                    $item['purpose'][$lang] = trim($purpose . ($item['parsed'] || '' === $time ? '' : ' (' . $time . ')'));
                }
                unset($item);
            }
            unset($service);
        }

        $existingGroups = [];
        foreach (Repository::groups() as $group) {
            $existingGroups[$group['key']] = $group['id'];
        }

        // Gruppen zusammenfuehren und Dienst -> Gruppe bestimmen.
        $groups = [];
        $serviceGroup = [];
        foreach ((array) ($data['cookiegroups'] ?? []) as $row) {
            $uid = $this->key((string) ($row['uid'] ?? ''));
            if ('' === $uid) {
                continue;
            }
            $lang = $code($row['clang_id'] ?? 0);
            $groups[$uid] ??= ['required' => false, 'name' => [], 'description' => []];
            $groups[$uid]['required'] = $groups[$uid]['required'] || str_contains((string) ($row['required'] ?? ''), '1');
            $groups[$uid]['name'][$lang] = trim((string) ($row['name'] ?? '')) ?: $uid;
            $groups[$uid]['description'][$lang] = trim(strip_tags((string) ($row['description'] ?? '')));
            foreach (array_filter(explode('|', (string) ($row['cookie'] ?? ''))) as $serviceUid) {
                $serviceGroup[$this->key($serviceUid)] ??= $uid;
            }
            if ('' !== trim((string) ($row['script'] ?? ''))) {
                $this->notes[] = 'Gruppe ' . $uid . ': Gruppen-Scripts gibt es nicht mehr – bitte als eigenen Dienst anlegen.';
            }
        }
        // Alte Standard-Gruppen auf die neuen Schluessel abbilden.
        $aliases = ['required' => 'necessary', 'necessary' => 'necessary', 'statistics' => 'statistics', 'marketing' => 'marketing', 'external' => 'media', 'services' => 'functional'];
        $groupIds = [];
        foreach ($groups as $uid => $group) {
            $target = $aliases[$uid] ?? $uid;
            if (!isset($existingGroups[$target])) {
                $existingGroups[$target] = Repository::saveGroup(0, ['key' => $target] + $group);
                ++$this->groups;
            }
            $groupIds[$uid] = $existingGroups[$target];
        }

        foreach ($services as $uid => $service) {
            // Der eigene Cookie des alten AddOns entfaellt.
            if (in_array($uid, ['consentmanager', 'consent_manager'], true)) {
                continue;
            }
            if (Repository::serviceKeyExists($uid)) {
                $this->notes[] = $uid . ': Schlüssel existiert bereits, übersprungen.';
                continue;
            }
            $groupId = $groupIds[$serviceGroup[$uid] ?? ''] ?? 0;
            if (0 === $groupId) {
                $this->notes[] = $uid . ': war keiner Gruppe zugeordnet, übersprungen.';
                continue;
            }
            foreach ($service['items'] as $item) {
                if (!$item['parsed']) {
                    $this->notes[] = $uid . ' / ' . $item['name'] . ': Laufzeit bitte prüfen (als „Sitzung“ übernommen, Originalangabe steht im Zweck).';
                }
            }
            // Importierte Dienste starten inaktiv: erst pruefen, dann freischalten.
            Repository::saveService(0, ['key' => $uid, 'group_id' => $groupId, 'status' => 0] + $service, array_values($service['items']));
            ++$this->services;
        }

        $known = array_column(Repository::domains(), 'host');
        foreach ((array) ($data['domains'] ?? []) as $row) {
            $host = Repository::normalizeHost((string) ($row['uid'] ?? ''));
            if ('' === $host || in_array($host, $known, true)) {
                continue;
            }
            Repository::saveDomain(0, $host, (int) ($row['privacy_policy'] ?? 0), (int) ($row['legal_notice'] ?? 0));
            $known[] = $host;
            ++$this->domains;
        }

        return ['groups' => $this->groups, 'services' => $this->services, 'domains' => $this->domains, 'notes' => array_values(array_unique($this->notes))];
    }

    private function key(string $uid): string
    {
        return substr(trim((string) preg_replace('~[^a-z0-9_]+~', '_', strtolower($uid)), '_'), 0, 64);
    }

    /** Genau ein Inline-<script> -> dessen Inhalt; reines JS ohne Tags bleibt; sonst null. */
    private function inlineScript(string $html): ?string
    {
        $html = trim($html);
        if (!str_contains($html, '<')) {
            return $html;
        }
        if (1 === preg_match('~^<script\b(?![^>]*\bsrc=)[^>]*>(.*)</script>$~is', $html, $match) && false === stripos($match[1], '<script')) {
            return trim($match[1]);
        }
        return null;
    }

    /** @return array{0: int, 1: string}|null */
    private function duration(string $time): ?array
    {
        $time = trim($time);
        if (1 === preg_match('~^(session|sitzung)$~i', $time)) {
            return [0, 'session'];
        }
        if (1 !== preg_match('~^(\d+)\s*(\p{L}+)$~u', $time, $match)) {
            return null;
        }
        $units = [
            'minutes' => '~^(min|minute|minuten|minutes)$~i', 'hours' => '~^(std|stunde|stunden|hour|hours)$~i',
            'days' => '~^(tag|tage|day|days)$~i', 'months' => '~^(monat|monate|month|months)$~i', 'years' => '~^(jahr|jahre|year|years)$~i',
        ];
        foreach ($units as $unit => $pattern) {
            if (1 === preg_match($pattern, $match[2])) {
                return [(int) $match[1], $unit];
            }
        }
        return null;
    }
}
