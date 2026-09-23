<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_addon;
use rex_dir;
use rex_file;
use rex_finder;
use rex_i18n;
use rex_response;

use function count;
use function is_array;
use function is_string;

/**
 * Export und Import von Dienste-Vorlagen (Format: presets/FORMAT.md).
 *
 * Eigene Vorlagen liegen in data/addons/consent_kit/presets/*.json und werden
 * von PresetRepository zusaetzlich geladen.
 */
final class PresetIo
{
    /** Ohne diese Angaben ist ein Eintrag keine Vorlage. */
    private const REQUIRED = ['key', 'name', 'group'];

    public static function dir(): string
    {
        return rex_addon::get('consent_kit')->getDataPath('presets');
    }

    /** Dateiname ohne Pfad, aber mit .json; verhindert Ausbrueche aus dem Vorlagen-Ordner. */
    public static function safeName(string $name): string
    {
        $name = preg_replace('~[^a-zA-Z0-9_-]+~', '-', pathinfo($name, PATHINFO_FILENAME) ?: '');
        $name = trim((string) $name, '-');
        return ('' === $name ? 'presets' : substr($name, 0, 64)) . '.json';
    }

    /**
     * Eigene Vorlagen-Dateien im Data-Ordner.
     *
     * @return list<array{file: string, name: string, size: int, count: int, keys: list<string>}>
     */
    public static function files(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (rex_finder::factory($dir)->filesOnly()->ignoreFiles('.*') as $path => $file) {
            if ('json' !== strtolower($file->getExtension())) {
                continue;
            }
            $data = json_decode((string) rex_file::get((string) $path), true);
            $keys = [];
            foreach ((array) (is_array($data) ? ($data['services'] ?? []) : []) as $service) {
                if (is_array($service) && isset($service['key']) && is_string($service['key'])) {
                    $keys[] = $service['key'];
                }
            }
            $out[] = [
                'file' => $file->getFilename(),
                'name' => $file->getFilename(),
                'size' => (int) $file->getSize(),
                'count' => count($keys),
                'keys' => $keys,
            ];
        }
        usort($out, static fn (array $a, array $b) => strcasecmp($a['file'], $b['file']));
        return $out;
    }

    public static function delete(string $file): bool
    {
        $path = self::dir() . '/' . self::safeName($file);
        return is_file($path) && rex_file::delete($path);
    }

    /* ------------------------------------------------------------ Export */

    /**
     * Angelegte Dienste als Vorlagen-JSON.
     *
     * @param list<int> $ids leer = alle Dienste
     */
    public static function export(array $ids = []): string
    {
        $groups = [];
        foreach (Repository::groups() as $group) {
            $groups[$group['id']] = $group['key'];
        }
        $services = [];
        foreach (Repository::services() as $service) {
            if ([] !== $ids && !in_array($service['id'], $ids, true)) {
                continue;
            }
            $services[] = self::serviceToPreset($service, $groups[$service['group_id']] ?? '');
        }
        return (string) json_encode(['services' => $services], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /** Download anstossen; beendet den Request. */
    public static function download(string $json, string $filename): never
    {
        rex_response::cleanOutputBuffers();
        rex_response::sendFile($json, 'application/json', 'attachment', $filename);
        exit;
    }

    /**
     * Ein gespeicherter Dienst in der Struktur einer Vorlage.
     *
     * Varianten, Domains, Status und Reihenfolge sind Angaben dieser Installation
     * und gehoeren nicht in eine Vorlage. Eingetragene Parameterwerte (IDs, Keys)
     * werden nicht exportiert, wohl aber ihre Platzhalter im Code.
     *
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    private static function serviceToPreset(array $service, string $group): array
    {
        $preset = [
            'key' => (string) $service['key'],
            'name' => (string) $service['name'],
            'group' => $group,
            'provider' => (string) $service['provider'],
            'privacy_url' => (string) $service['privacy_url'],
            'description' => (array) $service['description'],
            'params' => self::exportParams($service),
        ];
        foreach (Repository::CODE_FIELDS as $field) {
            $preset[$field] = (string) $service[$field];
        }
        $preset['gcm_signals'] = array_values((array) $service['gcm_signals']);
        $preset['embed_hosts'] = array_values((array) $service['embed_hosts']);
        $preset['items'] = [];
        foreach ((array) $service['items'] as $item) {
            $preset['items'][] = [
                'type' => (string) $item['type'],
                'name' => (string) $item['name'],
                'host' => (string) $item['host'],
                'duration' => ['value' => (int) $item['duration_value'], 'unit' => (string) $item['duration_unit']],
                'purpose' => (array) $item['purpose'],
            ];
        }
        // Aufruf-Vorlagen je Ereignis gehoeren zum Anbieter, die konfigurierten
        // Ausloeser dagegen zu dieser Installation.
        $source = PresetRepository::get((string) $service['preset']);
        $events = PresetRepository::events((string) $service['preset']);
        if ([] !== $events) {
            $preset['events'] = $events;
        }
        $preset['sources'] = null === $source ? [] : array_values(array_filter((array) ($source['sources'] ?? []), 'is_string'));
        $preset['verified'] = date('Y-m-d');
        return $preset;
    }

    /**
     * Parameter-Definitionen: aus der Ursprungsvorlage, sonst aus den im Code
     * gefundenen Platzhaltern. {{lang}} und {{domain}} setzt das AddOn selbst.
     *
     * @param array<string, mixed> $service
     * @return list<array<string, mixed>>
     */
    private static function exportParams(array $service): array
    {
        $defined = PresetRepository::params((string) $service['preset']);
        if ([] !== $defined) {
            return array_map(static fn (array $param) => array_filter([
                'key' => $param['key'],
                'label' => $param['label'],
                'placeholder' => $param['placeholder'],
                'pattern' => $param['pattern'],
            ], static fn (mixed $value) => [] !== $value && '' !== $value), $defined);
        }
        $code = '';
        foreach (Repository::CODE_FIELDS as $field) {
            $code .= (string) $service[$field] . "\n";
        }
        foreach ((array) $service['events'] as $row) {
            $code .= (string) (is_array($row) ? ($row['code'] ?? '') : '') . "\n";
        }
        preg_match_all('~\{\{([a-z0-9_]+)\}\}~i', $code, $matches);
        $out = [];
        foreach (array_unique($matches[1]) as $key) {
            if (in_array($key, ['lang', 'domain', 'label'], true)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => ['de' => $key, 'en' => $key]];
        }
        return $out;
    }

    /* ------------------------------------------------------------ Import */

    /**
     * Vorlagen-JSON pruefen und in den Data-Ordner schreiben.
     *
     * @return array{ok: bool, file: string, added: list<string>, errors: list<string>}
     */
    public static function import(string $json, string $filename, bool $overwrite = false): array
    {
        $result = ['ok' => false, 'file' => self::safeName($filename), 'added' => [], 'errors' => []];
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['services']) || !is_array($data['services'])) {
            $result['errors'][] = 'no_services';
            return $result;
        }
        $groups = array_column(Repository::groups(), 'key');
        $services = [];
        foreach (array_values($data['services']) as $index => $service) {
            $errors = self::validate($service, $groups, $index);
            if ([] !== $errors) {
                $result['errors'] = array_merge($result['errors'], $errors);
                continue;
            }
            $services[] = $service;
            $result['added'][] = (string) $service['key'];
        }
        if ([] === $services) {
            if ([] === $result['errors']) {
                $result['errors'][] = 'no_services';
            }
            return $result;
        }

        $dir = self::dir();
        rex_dir::create($dir);
        $path = $dir . '/' . $result['file'];
        if (!$overwrite && is_file($path)) {
            $result['file'] = self::uniqueName($dir, $result['file']);
            $path = $dir . '/' . $result['file'];
        }
        $written = rex_file::put($path, (string) json_encode(['services' => $services], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        if (!$written) {
            $result['errors'][] = 'write_failed';
            return $result;
        }
        $result['ok'] = true;
        return $result;
    }

    private static function uniqueName(string $dir, string $file): string
    {
        $base = pathinfo($file, PATHINFO_FILENAME);
        for ($i = 2; $i < 100; ++$i) {
            if (!is_file($dir . '/' . $base . '-' . $i . '.json')) {
                return $base . '-' . $i . '.json';
            }
        }
        return $base . '-' . time() . '.json';
    }

    /**
     * @param list<string> $groups
     * @return list<string> Fehlermeldungen, bereits uebersetzt
     */
    private static function validate(mixed $service, array $groups, int $index): array
    {
        $label = '#' . ($index + 1);
        if (!is_array($service)) {
            return [rex_i18n::rawMsg('consent_kit_preset_error_shape', $label)];
        }
        $errors = [];
        foreach (self::REQUIRED as $field) {
            if (!isset($service[$field]) || !is_string($service[$field]) || '' === trim($service[$field])) {
                $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_missing', $label, $field);
            }
        }
        if ([] !== $errors) {
            return $errors;
        }
        $label = (string) $service['key'];
        if (1 !== preg_match('~^[a-z0-9_]+$~', $label)) {
            $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_key', $label);
        }
        if (!in_array($service['group'], $groups, true)) {
            $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_group', $label, (string) $service['group'], implode(', ', $groups));
        }
        foreach ((array) ($service['items'] ?? []) as $item) {
            if (!is_array($item) || !isset($item['name']) || !is_string($item['name']) || '' === trim($item['name'])) {
                $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_item', $label);
                break;
            }
            if (!in_array((string) ($item['type'] ?? 'cookie'), Repository::ITEM_TYPES, true)) {
                $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_item_type', $label, (string) $item['type']);
                break;
            }
            $unit = (string) (is_array($item['duration'] ?? null) ? ($item['duration']['unit'] ?? 'session') : 'session');
            if (!in_array($unit, Repository::DURATION_UNITS, true)) {
                $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_duration', $label, $unit);
                break;
            }
        }
        foreach ((array) ($service['gcm_signals'] ?? []) as $signal) {
            if (!in_array($signal, Repository::GCM_SIGNALS, true)) {
                $errors[] = rex_i18n::rawMsg('consent_kit_preset_error_signal', $label, (string) $signal);
                break;
            }
        }
        return $errors;
    }
}
