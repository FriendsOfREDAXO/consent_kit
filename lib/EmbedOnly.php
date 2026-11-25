<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_i18n;

/**
 * „Hinweis nur, wenn nötig“: Dienste, die ausschliesslich ueber Einbettungen mit 2-Klick-Platzhalter laden.
 *
 * Solange nur solche Dienste aktiv sind, gibt es vor der Einwilligung nichts zu sperren: kein Script im
 * <head> oder <body>, kein Tracking, keine Cookies oder Storage-Eintraege. Die Einwilligung holt der
 * Platzhalter am Inhalt ein; der Hinweis beim Seitenaufruf entfaellt (Einstellung `only_needed`).
 */
final class EmbedOnly
{
    /**
     * Laeuft die Vorlage nur ueber Einbettungen? Ausdruecklich per `embed_only` in der Vorlage, sonst
     * abgeleitet: Gruppe „Externe Medien“ mit Domains eingebetteter Inhalte, ohne Consent Mode, ohne
     * Conversion-Ereignisse und ohne Code vor der Einwilligung (js_default). Ob ein Dienst wirklich so
     * ausgeliefert wird, prueft issues() am fertigen Dienst.
     *
     * @param array<string, mixed> $preset
     */
    public static function fromPreset(array $preset): bool
    {
        if (array_key_exists('embed_only', $preset)) {
            return (bool) $preset['embed_only'];
        }
        return 'media' === ($preset['group'] ?? '')
            && [] !== (array) ($preset['embed_hosts'] ?? [])
            && [] === (array) ($preset['gcm_signals'] ?? [])
            && [] === (array) ($preset['events'] ?? [])
            && '' === trim((string) ($preset['js_default'] ?? ''));
    }

    /**
     * Gruende, warum ein Dienst NICHT als „nur Einbettung mit Platzhalter“ gilt ([] = er gilt so).
     * Bedingungen: Merkmal gesetzt, nicht Statistik/Marketing, Domains eingebetteter Inhalte vorhanden,
     * kein Consent Mode, keine Ereignisse, kein Code vor der Einwilligung und Code (head/body/js_accept)
     * hoechstens vom Anbieter der Einbettung – etwa embed.js von instagram.com. Solcher Code laeuft
     * ohnehin erst nach der Einwilligung am Platzhalter.
     *
     * @param array<string, mixed> $service Dienst aus Repository::services()
     * @param string $groupKey Schluessel der Gruppe des Dienstes
     * @param array<string, string>|null $code aufgeloeste Code-Felder (Cache::resolveParams), null = Rohwerte
     * @return array<string, string> Schluessel => Text fuer das Backend
     */
    public static function issues(array $service, string $groupKey, ?array $code = null): array
    {
        $code ??= $service;
        $out = [];
        if (empty($service['embed_only'])) {
            $out['flag'] = rex_i18n::msg('consent_kit_embed_only_issue_flag');
        }
        if (in_array($groupKey, ['statistics', 'marketing'], true)) {
            $out['group'] = rex_i18n::msg('consent_kit_embed_only_issue_group');
        }
        $embed = array_values(array_filter(array_map(static fn ($h) => strtolower(trim((string) $h)), (array) ($service['embed_hosts'] ?? []))));
        if ([] === $embed) {
            $out['embed'] = rex_i18n::msg('consent_kit_embed_only_issue_embed');
        }
        if ([] !== (array) ($service['gcm_signals'] ?? [])) {
            $out['gcm'] = rex_i18n::msg('consent_kit_embed_only_issue_gcm');
        }
        if ([] !== (array) ($service['events'] ?? []) || '' !== trim((string) ($code['js_events'] ?? ''))) {
            $out['events'] = rex_i18n::msg('consent_kit_embed_only_issue_events');
        }
        if ('' !== trim((string) ($code['js_default'] ?? ''))) {
            $out['default'] = rex_i18n::msg('consent_kit_embed_only_issue_default');
        }
        $foreign = [];
        foreach (self::codeHosts($code) as $host) {
            if (!self::belongsTo($host, $embed)) {
                $foreign[] = $host;
            }
        }
        if ([] !== $foreign) {
            $out['code'] = rex_i18n::msg('consent_kit_embed_only_issue_code', implode(', ', array_unique($foreign)));
        }
        return $out;
    }

    /**
     * Hosts, die der Code eines Dienstes anspricht (Script-Quellen, URLs in JavaScript).
     *
     * @param array<string, mixed> $code
     * @return list<string>
     */
    public static function codeHosts(array $code): array
    {
        $hosts = [];
        foreach (['html_head', 'html_body', 'js_accept'] as $field) {
            $value = (string) ($code[$field] ?? '');
            if ('' === $value || !preg_match_all('~(?:https?:)?//([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+)~i', $value, $m)) {
                continue;
            }
            foreach ($m[1] as $host) {
                $hosts[strtolower($host)] = true;
            }
        }
        return array_keys($hosts);
    }

    /**
     * Gehoert der Host zum Anbieter der Einbettung? Vergleich ueber die registrierbare Domain der
     * Einbettungs-Domains (www.instagram.com → instagram.com), Subdomains zaehlen mit.
     *
     * @param list<string> $embedHosts
     */
    private static function belongsTo(string $host, array $embedHosts): bool
    {
        foreach ($embedHosts as $embed) {
            $base = implode('.', array_slice(explode('.', $embed), -2));
            if ($host === $embed || $host === $base || str_ends_with($host, '.' . $base)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Optionale Dienste der Konfiguration, die den Hinweis beim Seitenaufruf brauchen: [Schluessel => Name].
     * Leer = alle aktiven optionalen Dienste holen ihre Einwilligung am Platzhalter ein.
     *
     * @param array<string, mixed> $config Cache::build()
     * @return array<string, string>
     */
    public static function blockers(array $config): array
    {
        return (array) ($config['embedOnlyBlockers'] ?? []);
    }
}
