<?php

namespace KLXM\ConsentKit;

use rex_addon;
use rex_file;
use rex_finder;

/** Liest die Dienste-Vorlagen aus presets/*.json und data/addons/consent_kit/presets/*.json (Format: presets/FORMAT.md). */
final class PresetRepository
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $presets = null;

    /** @return array<string, array<string, mixed>> key => preset */
    public static function all(): array
    {
        if (null !== self::$presets) {
            return self::$presets;
        }
        self::$presets = [];
        $addon = rex_addon::get('consent_kit');
        // Eigene Vorlagen liegen update-sicher im Data-Ordner und ueberschreiben gleichnamige Schluessel.
        $files = array_merge(self::jsonFiles($addon->getPath('presets')), self::jsonFiles($addon->getDataPath('presets')));
        foreach ($files as $file) {
            $data = json_decode((string) rex_file::get($file), true);
            foreach ((array) ($data['services'] ?? []) as $preset) {
                if (is_array($preset) && isset($preset['key'], $preset['name'])) {
                    self::$presets[(string) $preset['key']] = $preset;
                }
            }
        }
        uasort(self::$presets, static fn (array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name']));
        return self::$presets;
    }

    /** @return list<string> */
    private static function jsonFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (rex_finder::factory($dir)->filesOnly()->ignoreFiles('.*') as $path => $file) {
            if ('json' === strtolower($file->getExtension())) {
                $files[] = (string) $path;
            }
        }
        sort($files);
        return $files;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Parameter-Definitionen (Label, Platzhalter, Muster) eines Presets.
     *
     * @return list<array{key: string, label: array<string, string>, placeholder: string, pattern: string}>
     */
    public static function params(string $key): array
    {
        $out = [];
        foreach ((array) (self::get($key)['params'] ?? []) as $param) {
            if (!is_array($param) || !isset($param['key'])) {
                continue;
            }
            $out[] = [
                'key' => (string) $param['key'],
                'label' => array_map('strval', (array) ($param['label'] ?? [])),
                'placeholder' => (string) ($param['placeholder'] ?? ''),
                'pattern' => (string) ($param['pattern'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Aufruf-Vorlagen je Ereignis (lead, registration, appointment, page_view).
     *
     * @return array<string, string>
     */
    public static function events(string $key): array
    {
        $out = [];
        foreach ((array) (self::get($key)['events'] ?? []) as $event => $call) {
            if (is_string($event) && is_string($call) && '' !== trim($call)) {
                $out[$event] = trim($call);
            }
        }
        return $out;
    }

    /**
     * Preset in die Form von Repository::saveService() bringen.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}|null
     */
    public static function toService(string $key): ?array
    {
        $preset = self::get($key);
        if (null === $preset) {
            return null;
        }
        $groupId = 0;
        foreach (Repository::groups() as $group) {
            if ($group['key'] === ($preset['group'] ?? '')) {
                $groupId = $group['id'];
            }
        }
        $data = [
            'key' => $key,
            'group_id' => $groupId,
            'status' => 1,
            'name' => (string) $preset['name'],
            'provider' => (string) ($preset['provider'] ?? ''),
            'privacy_url' => (string) ($preset['privacy_url'] ?? ''),
            'description' => (array) ($preset['description'] ?? []),
            'params' => [],
            'gcm_signals' => (array) ($preset['gcm_signals'] ?? []),
            'embed_hosts' => (array) ($preset['embed_hosts'] ?? []),
            'preset' => $key,
        ];
        foreach (['html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke'] as $field) {
            $data[$field] = (string) ($preset[$field] ?? '');
        }
        $items = [];
        foreach ((array) ($preset['items'] ?? []) as $item) {
            $items[] = [
                'type' => (string) ($item['type'] ?? 'cookie'),
                'name' => (string) ($item['name'] ?? ''),
                'host' => (string) ($item['host'] ?? ''),
                'duration_value' => (int) ($item['duration']['value'] ?? 0),
                'duration_unit' => (string) ($item['duration']['unit'] ?? 'session'),
                'purpose' => (array) ($item['purpose'] ?? []),
            ];
        }
        return [$data, $items];
    }
}
