<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_addon;

/**
 * Frontend-Texte: Standard de/en aus resources/texts.php, pro Sprache im Backend
 * ueberschreibbar (Config "texts"[code][key]). Leer = Standard.
 */
final class Texts
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $defaults = null;

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        if (null === self::$defaults) {
            /** @var array<string, array<string, string>> $data */
            $data = require rex_addon::get('consent_kit')->getPath('resources/texts.php');
            self::$defaults = $data;
        }
        return self::$defaults;
    }

    /** @return array<string, string> */
    public static function defaultsFor(string $code): array
    {
        $defaults = self::defaults();
        $code = I18n::normalize($code);
        return $defaults[$code] ?? $defaults[substr($code, 0, 2)] ?? $defaults['en'];
    }

    /** @return array<string, string> */
    public static function all(string $code): array
    {
        $code = I18n::normalize($code);
        $overrides = (array) rex_addon::get('consent_kit')->getConfig('texts', []);
        $texts = self::defaultsFor($code);
        foreach ((array) ($overrides[$code] ?? []) as $key => $value) {
            if (isset($texts[$key]) && is_string($value) && '' !== trim($value)) {
                $texts[$key] = trim($value);
            }
        }
        return $texts;
    }

    public static function duration(int $value, string $unit, string $code): string
    {
        $texts = self::all($code);
        if ('session' === $unit || 'persistent' === $unit) {
            return $texts['duration_' . $unit];
        }
        $key = 'duration_' . $unit . (1 === $value ? '_one' : '');
        return str_replace('{n}', (string) $value, $texts[$key] ?? '{n} ' . $unit);
    }
}
