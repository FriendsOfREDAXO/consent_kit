<?php

namespace KLXM\ConsentKit\Backend;

use KLXM\ConsentKit\Cache;
use KLXM\ConsentKit\I18n;
use KLXM\ConsentKit\Repository;
use KLXM\ConsentKit\Texts;
use rex_addon;

/**
 * Ergaenzt fehlende Uebersetzungen einer Sprache in einem Durchgang:
 * Gruppen, Dienste, Cookie-Zwecke und Frontend-Texte, jeweils aus der Standardsprache.
 */
final class BulkTranslator
{
    /** @return array{translated: int, skipped: int, failed: int} */
    public static function run(string $targetCode, string $sourceCode): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        set_time_limit(600);

        $fill = static function (array $values) use ($targetCode, $sourceCode, &$stats): array {
            $source = $values[$sourceCode] ?? '';
            if ('' === trim($source)) {
                return $values;
            }
            if ('' !== trim($values[$targetCode] ?? '')) {
                ++$stats['skipped'];
                return $values;
            }
            $result = WriteAssist::translate($source, $targetCode, $sourceCode);
            if (null === $result) {
                ++$stats['failed'];
                return $values;
            }
            ++$stats['translated'];
            $values[$targetCode] = $result;
            return $values;
        };

        foreach (Repository::groups() as $group) {
            $name = $fill($group['name']);
            $description = $fill($group['description']);
            if ($name !== $group['name'] || $description !== $group['description']) {
                Repository::saveGroup($group['id'], ['key' => $group['key'], 'required' => $group['required'], 'name' => $name, 'description' => $description]);
            }
        }

        foreach (Repository::services() as $service) {
            $description = $fill($service['description']);
            $changed = $description !== $service['description'];
            $items = [];
            foreach ($service['items'] as $item) {
                $purpose = $fill($item['purpose']);
                $changed = $changed || $purpose !== $item['purpose'];
                $items[] = ['purpose' => $purpose] + $item;
            }
            if ($changed) {
                Repository::saveService($service['id'], ['description' => $description] + $service, $items);
            }
        }

        // Frontend-Texte: Quelle ist der eigene DE-Text oder der mitgelieferte Standard.
        $addon = rex_addon::get('consent_kit');
        $all = (array) $addon->getConfig('texts', []);
        $sourceTexts = array_merge(Texts::defaultsFor($sourceCode), (array) ($all[$sourceCode] ?? []));
        $targetDefaults = Texts::defaults()[$targetCode] ?? Texts::defaults()[substr($targetCode, 0, 2)] ?? null;
        if (null === $targetDefaults) {
            $targetTexts = (array) ($all[$targetCode] ?? []);
            foreach ($sourceTexts as $key => $text) {
                $pair = $fill([$sourceCode => $text, $targetCode => (string) ($targetTexts[$key] ?? '')]);
                if ('' !== ($pair[$targetCode] ?? '')) {
                    $targetTexts[$key] = $pair[$targetCode];
                }
            }
            $all[$targetCode] = $targetTexts;
            $addon->setConfig('texts', array_filter($all));
        }

        Cache::clear();
        return $stats;
    }

    /**
     * Alle Sprachen ausser der Standardsprache.
     *
     * @return array<string, string>
     */
    public static function targetLanguages(): array
    {
        $languages = I18n::languages();
        array_shift($languages);
        return $languages;
    }
}
