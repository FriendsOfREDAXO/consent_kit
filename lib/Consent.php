<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_clang;
use rex_request;

/**
 * Oeffentliche PHP-API fuer Templates und Module.
 *
 *   Consent::head()                                   // Einbindung im <head> (statt REX_CONSENT_KIT[])
 *   Consent::has('matomo')
 *   Consent::embed('youtube', '<iframe …></iframe>')
 *   Consent::oembed($html)                            // <oembed>-Tags aus CKE5/TinyMCE sperren
 *   Consent::overview()                               // Dienste-Liste fuer die Datenschutzerklaerung
 */
final class Consent
{
    public const COOKIE = 'consent_kit';

    /** @var array{id: int, host: string, privacy_article_id: int, imprint_article_id: int}|null */
    private static ?array $domain = null;

    /**
     * Domain-Datensatz zum aktuellen Host, sonst der Fallback "*".
     *
     * @return array{id: int, host: string, privacy_article_id: int, imprint_article_id: int}
     */
    public static function domain(): array
    {
        if (null !== self::$domain) {
            return self::$domain;
        }
        $host = Repository::normalizeHost((string) rex_request::server('HTTP_HOST', 'string', ''));
        $fallback = ['id' => 0, 'host' => '*', 'privacy_article_id' => 0, 'imprint_article_id' => 0];
        $match = null;
        foreach (Repository::domains() as $domain) {
            if ($domain['host'] === $host) {
                $match = $domain;
            }
            if ('*' === $domain['host']) {
                $fallback = $domain;
            }
        }
        if (null !== $match) {
            // Ohne eigene Rechtstexte gelten die von "Alle Domains".
            $match['privacy_article_id'] = $match['privacy_article_id'] ?: $fallback['privacy_article_id'];
            $match['imprint_article_id'] = $match['imprint_article_id'] ?: $fallback['imprint_article_id'];
            return self::$domain = $match;
        }
        return self::$domain = $fallback;
    }

    /**
     * Ausgabe fuer den <head> des Templates – Alternative zur automatischen
     * Einbindung und zu REX_CONSENT_KIT[]. Moeglichst weit oben platzieren.
     */
    public static function head(): string
    {
        return Frontend::head();
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return Cache::config(self::domain(), rex_clang::getCurrent()->getCode());
    }

    /**
     * Inhalt des Consent-Cookies oder null, wenn keiner (gueltig) gesetzt ist.
     *
     * @return array{id: string, e: int, rev: int, ts: int, a: array<string, string>, r: array<string, string>}|null
     */
    public static function state(): ?array
    {
        $raw = rex_request::cookie(self::COOKIE, 'string', '');
        if ('' === $raw) {
            return null;
        }
        return self::parseState(json_decode($raw, true));
    }

    /**
     * @return array{id: string, e: int, rev: int, ts: int, a: array<string, string>, r: array<string, string>}|null
     */
    public static function parseState(mixed $data): ?array
    {
        if (!is_array($data) || !isset($data['id']) || !is_string($data['id']) || 1 !== preg_match('~^[0-9a-f-]{36}$~', $data['id'])) {
            return null;
        }
        $lists = [];
        foreach (['a', 'r'] as $list) {
            $lists[$list] = [];
            foreach ((array) ($data[$list] ?? []) as $key => $hash) {
                if (is_string($key) && is_string($hash) && 1 === preg_match('~^[a-z0-9_]{1,64}$~', $key) && 1 === preg_match('~^[0-9a-f]{6}$~', $hash)) {
                    $lists[$list][$key] = $hash;
                }
            }
        }
        return [
            'id' => $data['id'],
            'e' => (int) ($data['e'] ?? 0),
            'rev' => (int) ($data['rev'] ?? 0),
            'ts' => (int) ($data['ts'] ?? 0),
            'a' => $lists['a'],
            'r' => $lists['r'],
        ];
    }

    /**
     * true, wenn der Dienst notwendig ist oder der Besucher ihm in der
     * aktuellen Fassung zugestimmt hat.
     */
    public static function has(string $serviceKey): bool
    {
        $config = self::config();
        $state = self::state();
        foreach ($config['groups'] as $group) {
            foreach ($group['services'] as $service) {
                if ($service['key'] !== $serviceKey) {
                    continue;
                }
                if ($group['required']) {
                    return true;
                }
                return null !== $state
                    && $state['e'] === $config['epoch']
                    && ($state['a'][$serviceKey] ?? null) === $service['h'];
            }
        }
        return false;
    }

    /**
     * Alle Dienste der aktuellen Domain als HTML – gedacht fuer die Datenschutzerklaerung.
     * Texte und Laufzeiten kommen in der aktuellen Sprache.
     *
     * @param int $headingLevel Ebene der Gruppen-Ueberschrift (Dienste eine Ebene tiefer)
     */
    public static function overview(int $headingLevel = 3): string
    {
        $config = self::config();
        $texts = $config['texts'];
        $group = 'h' . max(1, min(5, $headingLevel));
        $service = 'h' . (max(1, min(5, $headingLevel)) + 1);
        $e = static fn (string $value): string => rex_escape($value);

        $out = '<div class="consent-kit-overview">';
        foreach ($config['groups'] as $entry) {
            $out .= '<' . $group . '>' . $e($entry['name']) . '</' . $group . '><p>' . $e($entry['description']) . '</p>';
            foreach ($entry['services'] as $item) {
                $out .= '<' . $service . '>' . $e($item['name']) . '</' . $service . '>';
                if ('' !== $item['description']) {
                    $out .= '<p>' . $e($item['description']) . '</p>';
                }
                if ('' !== $item['provider']) {
                    $out .= '<p><strong>' . $e($texts['provider']) . ':</strong> ' . $e($item['provider']);
                    if ('' !== $item['privacyUrl']) {
                        $out .= ' – <a href="' . $e($item['privacyUrl']) . '" rel="noopener noreferrer">' . $e($texts['privacy_policy']) . '</a>';
                    }
                    $out .= '</p>';
                }
                if ([] === $item['items']) {
                    continue;
                }
                $out .= '<table><caption>' . $e($texts['storage']) . '</caption><thead><tr>';
                foreach (['col_name', 'col_type', 'col_host', 'col_duration', 'col_purpose'] as $column) {
                    $out .= '<th scope="col">' . $e($texts[$column]) . '</th>';
                }
                $out .= '</tr></thead><tbody>';
                foreach ($item['items'] as $row) {
                    $out .= '<tr><td>' . $e($row['name']) . '</td><td>' . $e($texts['type_' . $row['type']] ?? $row['type']) . '</td><td>' . $e($row['host']) . '</td><td>' . $e($row['duration']) . '</td><td>' . $e($row['purpose']) . '</td></tr>';
                }
                $out .= '</tbody></table>';
            }
        }
        return $out . '<p><a href="#consent-kit">' . $e($texts['trigger']) . '</a></p></div>';
    }

    /**
     * Wandelt <oembed>-Tags von CKEditor 5 / TinyMCE in gesperrte Platzhalter um –
     * fuer Modulausgaben, wenn die automatische Umwandlung abgeschaltet ist.
     */
    public static function oembed(string $html): string
    {
        return Oembed::render($html, self::config());
    }

    /**
     * Verpackt externes Markup (iframe, Script-Embed) so, dass es erst nach
     * Einwilligung in den Dienst geladen wird.
     *
     * @param array{title?: string, ratio?: string} $options ratio z. B. "16/9"
     */
    public static function embed(string $serviceKey, string $html, array $options = []): string
    {
        return Embed::wrap($serviceKey, $html, $options);
    }
}
