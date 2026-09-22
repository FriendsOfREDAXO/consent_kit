<?php

namespace KLXM\ConsentKit\Backend;

use FriendsOfREDAXO\WriteAssist\AutoTranslateService;
use FriendsOfREDAXO\WriteAssist\WriteAssistAiFactory;
use KLXM\ConsentKit\I18n;
use rex_clang;
use Throwable;
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

    /** Schaltflaeche "alle leeren Sprachfelder dieses Formulars uebersetzen". */
    public static function bulkButton(): string
    {
        if (!self::available()) {
            return '';
        }
        return '<button type="button" class="btn btn-default" data-ck-translate-all data-msg-progress="' . rex_escape(rex_i18n::rawMsg('consent_kit_translate_progress')) . '" data-msg-none="' . rex_escape(rex_i18n::rawMsg('consent_kit_translate_none')) . '"><i class="rex-icon fa-language" aria-hidden="true"></i> ' . rex_i18n::msg('consent_kit_translate_all') . '</button>';
    }

    /**
     * Uebersetzt serverseitig (DeepL oder Text-KI, je nach WriteAssist-Einstellung).
     * Liefert null, wenn der Sprachcode keiner REDAXO-Sprache entspricht oder der Dienst fehlschlaegt.
     */
    public static function translate(string $text, string $targetCode, string $sourceCode): ?string
    {
        $target = self::clangId($targetCode);
        $source = self::clangId($sourceCode);
        if (null === $target || null === $source || '' === trim($text)) {
            return null;
        }
        try {
            $result = trim(AutoTranslateService::translateText($text, $target, $source));
        } catch (Throwable) {
            return null;
        }
        return '' === $result ? null : $result;
    }

    private static function clangId(string $code): ?int
    {
        $code = I18n::normalize($code);
        foreach (rex_clang::getAll() as $clang) {
            if (I18n::normalize($clang->getCode()) === $code) {
                return $clang->getId();
            }
        }
        return null;
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
