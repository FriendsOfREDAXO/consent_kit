<?php

namespace FriendsOfRedaxo\ConsentKit;

/**
 * Conversion-Ereignisse ohne Programmierung: "Wenn Klick auf … / Seite … /
 * Formular … dann melde Anfrage". Die Vorlage kennt den Aufruf je Anbieter,
 * hier entsteht daraus das JavaScript, das nach Einwilligung laeuft.
 */
final class Events
{
    public const TRIGGERS = ['click', 'page', 'form'];
    /** Ereignisse mit Aufruf-Vorlage je Anbieter; "custom" = eigener Code. */
    public const TYPES = ['lead', 'registration', 'appointment', 'page_view', 'custom'];

    /**
     * @param array<mixed> $raw
     * @return list<array{trigger: string, target: string, event: string, label: string, code: string}>
     */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $target = trim((string) ($row['target'] ?? ''));
            $event = in_array($row['event'] ?? '', self::TYPES, true) ? (string) $row['event'] : 'custom';
            $code = trim((string) ($row['code'] ?? ''));
            if ('' === $target || ('custom' === $event && '' === $code)) {
                continue;
            }
            $out[] = [
                'trigger' => in_array($row['trigger'] ?? '', self::TRIGGERS, true) ? (string) $row['trigger'] : 'click',
                'target' => $target,
                'event' => $event,
                'label' => trim((string) ($row['label'] ?? '')),
                'code' => 'custom' === $event ? $code : '',
            ];
        }
        return $out;
    }

    /**
     * JavaScript fuer alle Ereignisse eines Dienstes. Platzhalter {{param}} bleiben
     * stehen und werden wie die anderen Code-Felder aufgeloest.
     *
     * @param list<array{trigger: string, target: string, event: string, label: string, code: string}> $rows
     * @param array<string, string> $templates event => Aufruf mit {{label}} und {{param}}
     */
    public static function build(array $rows, array $templates): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $call = 'custom' === $row['event'] ? $row['code'] : (string) ($templates[$row['event']] ?? '');
            if ('' === trim($call)) {
                continue;
            }
            $call = str_replace('{{label}}', addcslashes($row['label'], "\\'\""), $call);
            $fn = 'function () { ' . $call . ' }';
            $target = (string) json_encode($row['target'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
            $parts[] = match ($row['trigger']) {
                'page' => 'ck.page(' . $target . ', ' . $fn . ');',
                'form' => 'ck.form(' . $target . ', ' . $fn . ');',
                default => 'ck.click(' . $target . ', ' . $fn . ');',
            };
        }
        if ([] === $parts) {
            return '';
        }
        // Kleine Laufzeit: Klick per Delegation, Formular beim Absenden, Seite beim Laden.
        return '(function () { var ck = {'
            . ' run: function (fn) { try { fn(); } catch (e) { console.error("[consent-kit] event", e); } },'
            . ' click: function (sel, fn) { document.addEventListener("click", function (e) { if (e.target.closest && e.target.closest(sel)) ck.run(fn); }); },'
            . ' form: function (sel, fn) { document.addEventListener("submit", function (e) { if (e.target.matches && e.target.matches(sel)) ck.run(fn); }); },'
            . ' page: function (path, fn) { var here = location.pathname + location.search; if (here === path || here.indexOf(path) === 0 || (path.indexOf("/") !== 0 && here.indexOf(path) !== -1)) ck.run(fn); }'
            . ' }; ' . implode(' ', $parts) . ' })();';
    }
}
