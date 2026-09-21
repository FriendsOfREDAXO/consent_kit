<?php

namespace KLXM\ConsentKit\Api;

use KLXM\ConsentKit\Catalog;
use KLXM\ConsentKit\Repository;
use rex;
use rex_api_function;
use rex_api_result;
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
                $known[] = [$regex, $item['type'], $service['name'], $service['status']];
            }
        }
        $hasCatalog = Catalog::count() > 0;

        $result = [];
        foreach (rex_request::post('entries', 'array', []) as $entry) {
            if (!is_array($entry) || !isset($entry['name'], $entry['type']) || count($result) >= 300) {
                continue;
            }
            $name = substr((string) $entry['name'], 0, 191);
            $row = ['name' => $name, 'type' => (string) $entry['type'], 'service' => null, 'active' => false, 'catalog' => null];
            foreach ($known as [$regex, $type, $serviceName, $status]) {
                if ($type === $row['type'] && 1 === preg_match($regex, $name)) {
                    $row['service'] = $serviceName;
                    $row['active'] = $status;
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
}
