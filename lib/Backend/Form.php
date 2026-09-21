<?php

namespace KLXM\ConsentKit\Backend;

use KLXM\ConsentKit\I18n;

/** Kleine Bausteine fuer die Bootstrap-3-Formulare der Backend-Seiten. */
final class Form
{
    private static int $counter = 0;

    /**
     * Escaped Text fuer HTML, ohne bereits vorhandene Entities erneut zu kodieren:
     * rex_i18n::msg() liefert schon escapte Texte ("&lt;head&gt;"), Daten aus der
     * Datenbank dagegen rohe – beide duerfen hier hinein.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /** @param array<string, string|int|bool> $attributes */
    public static function attributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if (false === $value || '' === $value) {
                continue;
            }
            $out .= ' ' . $name . (true === $value ? '' : '="' . self::e((string) $value) . '"');
        }
        return $out;
    }

    public static function id(): string
    {
        return 'ck-field-' . ++self::$counter;
    }

    public static function group(string $id, string $label, string $control, string $help = ''): string
    {
        $helpId = $id . '-help';
        if ('' !== $help) {
            $control = str_replace('data-help', 'aria-describedby="' . $helpId . '"', $control);
            $help = '<p class="help-block" id="' . $helpId . '">' . $help . '</p>';
        } else {
            $control = str_replace(' data-help', '', $control);
        }
        return '<div class="form-group ck-field' . (str_contains($control, 'aria-invalid="true"') ? ' has-error' : '') . '"><label class="control-label" for="' . $id . '">' . self::e($label) . '</label>' . $control . $help . '</div>';
    }

    /** @param array<string, string|int|bool> $attributes */
    public static function text(string $name, string $label, string $value, string $help = '', array $attributes = []): string
    {
        $id = self::id();
        $attributes = ['type' => 'text'] + $attributes;
        $control = '<input class="form-control" id="' . $id . '" name="' . self::e($name) . '" value="' . self::e($value) . '"' . self::attributes($attributes) . ' data-help>';
        return self::group($id, $label, $control, $help);
    }

    /** @param array<string, string|int|bool> $attributes */
    public static function textarea(string $name, string $label, string $value, string $help = '', array $attributes = []): string
    {
        $id = self::id();
        $attributes += ['rows' => 3];
        $class = 'form-control' . (isset($attributes['class']) ? ' ' . $attributes['class'] : '');
        unset($attributes['class']);
        $control = '<textarea class="' . $class . '" id="' . $id . '" name="' . self::e($name) . '"' . self::attributes($attributes) . ' data-help>' . self::e($value) . '</textarea>';
        return self::group($id, $label, $control, $help);
    }

    /** @param array<string|int, string> $options */
    public static function select(string $name, string $label, string|int $value, array $options, string $help = ''): string
    {
        $id = self::id();
        $control = '<select class="form-control" id="' . $id . '" name="' . self::e($name) . '" data-help>';
        foreach ($options as $optionValue => $optionLabel) {
            $control .= '<option value="' . self::e((string) $optionValue) . '"' . ((string) $optionValue === (string) $value ? ' selected' : '') . '>' . self::e($optionLabel) . '</option>';
        }
        $control .= '</select>';
        return self::group($id, $label, $control, $help);
    }

    public static function checkbox(string $name, string $label, bool $checked, string $help = '', string $value = '1'): string
    {
        $id = self::id();
        return '<div class="checkbox ck-check"><label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="' . self::e($name) . '" value="' . self::e($value) . '"' . ($checked ? ' checked' : '') . ('' !== $help ? ' aria-describedby="' . $id . '-help"' : '') . '> ' . self::e($label) . '</label>'
            . ('' !== $help ? '<p class="help-block" id="' . $id . '-help">' . $help . '</p>' : '') . '</div>';
    }

    /**
     * Radio-Karten fuer wenige, erklaerungsbeduerftige Optionen.
     *
     * @param array<string, array{0: string, 1: string}> $options value => [label, description]
     */
    public static function choice(string $name, string $legend, string $value, array $options): string
    {
        $out = '<fieldset class="ck-choice"><legend>' . self::e($legend) . '</legend><div class="ck-choice-grid">';
        foreach ($options as $optionValue => [$label, $description]) {
            $id = self::id();
            $out .= '<label class="ck-choice-card" for="' . $id . '"><input type="radio" id="' . $id . '" name="' . self::e($name) . '" value="' . self::e($optionValue) . '"' . ($optionValue === $value ? ' checked' : '') . '>'
                . '<span class="ck-choice-label">' . self::e($label) . '</span><span class="ck-choice-text">' . self::e($description) . '</span></label>';
        }
        return $out . '</div></fieldset>';
    }

    /**
     * Ein Feld pro Sprache, Standardsprache zuerst.
     *
     * @param array<string, string> $values
     */
    public static function i18n(string $name, string $label, array $values, bool $multiline = false, string $help = ''): string
    {
        $out = '<fieldset class="ck-i18n"><legend>' . self::e($label) . '</legend>';
        $languages = I18n::languages();
        // Werte in Sprachen, die es (noch) nicht als clang gibt, nicht stillschweigend verlieren.
        foreach (array_keys($values) as $code) {
            $languages += [$code => strtoupper($code)];
        }
        foreach ($languages as $code => $language) {
            $id = self::id();
            $field = self::e($name . '[' . $code . ']');
            $value = self::e($values[$code] ?? '');
            $control = $multiline
                ? '<textarea class="form-control" rows="2" id="' . $id . '" name="' . $field . '" lang="' . self::e(substr($code, 0, 2)) . '">' . $value . '</textarea>'
                : '<input type="text" class="form-control" id="' . $id . '" name="' . $field . '" value="' . $value . '" lang="' . self::e(substr($code, 0, 2)) . '">';
            $out .= '<div class="input-group ck-i18n-row"><label class="input-group-addon" for="' . $id . '" title="' . self::e($language) . '">' . self::e(strtoupper($code)) . '<span class="sr-only"> – ' . self::e($label . ' (' . $language . ')') . '</span></label>' . $control . '</div>';
        }
        if ('' !== $help) {
            $out .= '<p class="help-block">' . $help . '</p>';
        }
        return $out . '</fieldset>';
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value): array
    {
        $out = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && is_string($item)) {
                    $out[$key] = $item;
                }
            }
        }
        return $out;
    }
}
