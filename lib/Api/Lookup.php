<?php

namespace FriendsOfRedaxo\ConsentKit\Api;

use FriendsOfRedaxo\ConsentKit\Catalog;
use FriendsOfRedaxo\ConsentKit\Repository;
use FriendsOfRedaxo\ConsentKit\Texts;
use rex;
use rex_api_function;
use rex_api_result;
use rex_i18n;
use rex_request;
use rex_response;

/** Backend-Scanner: ordnet gefundene Cookie-/Storage-Namen Diensten oder dem Katalog zu. */
final class Lookup extends rex_api_function
{
    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        if (!rex::getUser()?->isAdmin()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'Forbidden']);
            exit;
        }

        $known = [];
        foreach (Repository::services() as $service) {
            foreach ($service['items'] as $item) {
                $regex = '~^' . str_replace('\*', '.*', preg_quote($item['name'], '~')) . '$~';
                $known[] = [$regex, $item['type'], $service['name'], $service['status'], self::duration($item)];
            }
        }
        $hasCatalog = Catalog::count() > 0;

        $result = [];
        foreach (rex_request::post('entries', 'array', []) as $entry) {
            if (!is_array($entry) || !isset($entry['name'], $entry['type']) || count($result) >= 300) {
                continue;
            }
            $name = substr((string) $entry['name'], 0, 191);
            $row = ['name' => $name, 'type' => (string) $entry['type'], 'service' => null, 'active' => false, 'documented' => null, 'catalog' => null];
            foreach ($known as [$regex, $type, $serviceName, $status, $duration]) {
                if ($type === $row['type'] && 1 === preg_match($regex, $name)) {
                    $row['service'] = $serviceName;
                    $row['active'] = $status;
                    $row['documented'] = $duration;
                    break;
                }
            }
            if (null === $row['service'] && $hasCatalog) {
                $match = Catalog::lookup($name);
                if (null !== $match) {
                    $row['catalog'] = [
                        'platform' => (string) $match['platform'],
                        'category' => (string) $match['category'],
                        'description' => (string) $match['description'],
                        'retention' => (string) $match['retention'],
                    ];
                }
            }
            $result[] = $row;
        }
        rex_response::sendJson(['entries' => $result, 'catalog' => $hasCatalog]);
        exit;
    }

    /**
     * Dokumentierte Laufzeit: Text fuer die Anzeige plus Tage zum Vergleich (null = Sitzung/unbegrenzt).
     *
     * @param array<string, mixed> $item
     * @return array{text: string, days: float|null}
     */
    private static function duration(array $item): array
    {
        $unit = (string) $item['duration_unit'];
        $value = (int) $item['duration_value'];
        $text = Texts::duration($value, $unit, rex_i18n::getLanguage());
        if ('session' === $unit || 'persistent' === $unit) {
            return ['text' => $text, 'days' => null];
        }
        $perUnit = ['minutes' => 1 / 1440, 'hours' => 1 / 24, 'days' => 1, 'months' => 30.44, 'years' => 365.25];
        return ['text' => $text, 'days' => $value * ($perUnit[$unit] ?? 1)];
    }
}
