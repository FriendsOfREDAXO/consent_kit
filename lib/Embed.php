<?php

namespace KLXM\ConsentKit;

/**
 * 2-Klick-Einbettung. Das Original-Markup liegt inert in einem <template> und
 * wird von <consent-embed> erst nach Einwilligung in die Seite geklont.
 */
final class Embed
{
    /** @param array{title?: string, ratio?: string} $options */
    public static function wrap(string $serviceKey, string $html, array $options = []): string
    {
        $attributes = ' service="' . rex_escape($serviceKey) . '"';
        if (isset($options['title']) && '' !== $options['title']) {
            $attributes .= ' label="' . rex_escape($options['title']) . '"';
        }
        if (isset($options['ratio']) && 1 === preg_match('~^\d+(\.\d+)?\s*/\s*\d+(\.\d+)?$~', $options['ratio'])) {
            $attributes .= ' style="--ck-embed-ratio:' . $options['ratio'] . '"';
        }
        return '<consent-embed' . $attributes . '><template>' . $html . '</template></consent-embed>';
    }

    /**
     * Ersetzt iframes bekannter Dienste (embed_hosts) im fertigen HTML.
     * Bereits verpackte iframes (innerhalb <template>) bleiben unberuehrt.
     *
     * @param array<string, mixed> $config
     */
    public static function filter(string $html, array $config): string
    {
        $hosts = [];
        foreach ($config['groups'] as $group) {
            if ($group['required']) {
                continue;
            }
            foreach ($group['services'] as $service) {
                foreach ($service['hosts'] as $host) {
                    $hosts[$host] = $service['key'];
                }
            }
        }
        if ([] === $hosts || false === stripos($html, '<iframe')) {
            return $html;
        }

        // Vorhandene <consent-embed>-Bloecke ausklammern, damit nichts doppelt verpackt wird.
        $parts = preg_split('~(<consent-embed\b.*?</consent-embed>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return $html;
        }
        foreach ($parts as $index => $part) {
            if (1 === $index % 2) {
                continue;
            }
            $parts[$index] = (string) preg_replace_callback(
                '~<iframe\b[^>]*>.*?</iframe>~is',
                static function (array $match) use ($hosts): string {
                    if (1 !== preg_match('~\ssrc\s*=\s*(["\'])(.*?)\1~is', $match[0], $src)) {
                        return $match[0];
                    }
                    $serviceKey = self::matchHost(html_entity_decode($src[2]), $hosts);
                    if (null === $serviceKey) {
                        return $match[0];
                    }
                    $options = [];
                    if (1 === preg_match('~\stitle\s*=\s*(["\'])(.*?)\1~is', $match[0], $title)) {
                        $options['title'] = html_entity_decode($title[2]);
                    }
                    return self::wrap($serviceKey, $match[0], $options);
                },
                $part,
            );
        }
        return implode('', $parts);
    }

    /**
     * Dienst, dem der Host einer URL zugeordnet ist (embed_hosts, Subdomains inklusive).
     *
     * @param array<string, mixed> $config
     */
    public static function serviceForUrl(string $url, array $config): ?string
    {
        $hosts = [];
        foreach ($config['groups'] as $group) {
            foreach ($group['services'] as $service) {
                foreach ($service['hosts'] as $host) {
                    $hosts[$host] = $service['key'];
                }
            }
        }
        return self::matchHost($url, $hosts);
    }

    /** @param array<string, string> $hosts host => service key */
    private static function matchHost(string $url, array $hosts): ?string
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ('' === $host) {
            return null;
        }
        foreach ($hosts as $candidate => $serviceKey) {
            if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return $serviceKey;
            }
        }
        return null;
    }
}
