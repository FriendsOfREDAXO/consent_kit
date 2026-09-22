<?php

namespace KLXM\ConsentKit\Backend;

use FriendsOfREDAXO\WriteAssist\WriteAssistAiFactory;
use rex_addon;
use rex_escape;
use rex_i18n;

/**
 * Optionale Anbindung an das AddOn writeassist: Sprachfelder aus der
 * Standardsprache uebersetzen (Endpunkt rex-api-call=writeassist_translate).
 */
final class WriteAssist
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (null !== self::$available) {
            return self::$available;
        }
        $addon = rex_addon::get('writeassist');
        if (!$addon->isAvailable()) {
            return self::$available = false;
        }
        // Gleiche Logik wie AutoTranslateService::hasConfiguredProvider() (dort privat).
        if ('ai' === $addon->getConfig('translation_provider', 'deepl')) {
            return self::$available = class_exists(WriteAssistAiFactory::class) && WriteAssistAiFactory::factory()->isConfigured();
        }
        return self::$available = '' !== (string) $addon->getConfig('api_key', '');
    }

    /**
     * Schaltflaeche, die $targetId aus dem Feld $sourceId (bzw. $sourceText) fuellt.
     */
    public static function button(string $targetId, string $targetCode, string $sourceCode, ?string $sourceId = null, ?string $sourceText = null): string
    {
        if (!self::available()) {
            return '';
        }
        $label = rex_i18n::msg('consent_kit_translate_from', strtoupper(substr($sourceCode, 0, 2)));
        return '<button type="button" class="btn btn-default ck-translate" data-ck-translate="' . rex_escape($targetId) . '" data-target-lang="' . rex_escape(strtoupper(substr($targetCode, 0, 2))) . '" data-source-lang="' . rex_escape(strtoupper(substr($sourceCode, 0, 2))) . '"'
            . (null !== $sourceId ? ' data-source-id="' . rex_escape($sourceId) . '"' : '')
            . (null !== $sourceText ? ' data-source-text="' . rex_escape($sourceText) . '"' : '')
            . ' data-msg-empty="' . rex_escape(rex_i18n::rawMsg('consent_kit_translate_empty')) . '" data-msg-overwrite="' . rex_escape(rex_i18n::rawMsg('consent_kit_translate_overwrite')) . '" data-msg-done="' . rex_escape(rex_i18n::rawMsg('consent_kit_translate_done')) . '"'
            . ' title="' . $label . '"><i class="rex-icon fa-language" aria-hidden="true"></i><span class="sr-only">' . $label . '</span></button>';
    }
}
