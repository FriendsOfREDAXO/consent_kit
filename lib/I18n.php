<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_clang;

/**
 * Uebersetzbare Felder liegen als JSON {"de": "...", "en": "..."} in einer
 * Spalte, Schluessel ist der clang-Code. Dadurch gibt es keine Datensatz-
 * Duplikate pro Sprache und Presets koennen de/en direkt mitbringen.
 */
final class I18n
{
    /** @return array<string, string> */
    public static function decode(?string $json): array
    {
        if (null === $json || '' === $json) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $code => $value) {
            if (is_string($code) && is_string($value) && '' !== trim($value)) {
                $out[$code] = $value;
            }
        }
        return $out;
    }

    /** @param array<string, string> $values */
    public static function encode(array $values): string
    {
        $values = array_filter(array_map('trim', $values), static fn (string $v) => '' !== $v);
        return (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Fallback: exakter Code, Sprachanteil ("de" aus "de_at"), Standardsprache, Englisch, erster Eintrag.
     *
     * @param array<string, string> $values
     */
    public static function pick(array $values, string $code): string
    {
        $code = self::normalize($code);
        $candidates = [$code, substr($code, 0, 2), self::defaultCode(), 'en'];
        foreach ($candidates as $candidate) {
            if (isset($values[$candidate])) {
                return $values[$candidate];
            }
        }
        foreach ($values as $key => $value) {
            if (substr(self::normalize($key), 0, 2) === substr($code, 0, 2)) {
                return $value;
            }
        }
        return [] === $values ? '' : (string) reset($values);
    }

    public static function normalize(string $code): string
    {
        return strtolower(str_replace('-', '_', trim($code)));
    }

    public static function defaultCode(): string
    {
        $clang = class_exists(rex_clang::class) ? rex_clang::get(rex_clang::getStartId()) : null;
        return null === $clang ? 'de' : self::normalize($clang->getCode());
    }

    /**
     * Codes aller Sprachen, Standardsprache zuerst.
     *
     * @return array<string, string> code => name
     */
    public static function languages(): array
    {
        $out = [];
        foreach (rex_clang::getAll() as $clang) {
            $out[self::normalize($clang->getCode())] = trim($clang->getName());
        }
        $default = self::defaultCode();
        if (isset($out[$default])) {
            $out = [$default => $out[$default]] + $out;
        }
        return $out;
    }
}
