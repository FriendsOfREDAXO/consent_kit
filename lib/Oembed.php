<?php

namespace KLXM\ConsentKit;

/**
 * Wandelt das Speicherformat von CKEditor 5 und TinyMCE (for_oembed)
 *
 *   <figure class="media"><oembed url="https://www.youtube.com/watch?v=…"></oembed></figure>
 *
 * in einen gesperrten Platzhalter um. Bekannte Anbieter bekommen ihre Embed-URL
 * (YouTube ueber youtube-nocookie.com, Vimeo mit dnt=1); andere URLs werden nur
 * dann als iframe eingebettet, wenn ihr Host bei einem Dienst hinterlegt ist.
 */
final class Oembed
{
    /** @param array<string, mixed> $config */
    public static function render(string $html, array $config): string
    {
        if (false === stripos($html, '<oembed')) {
            return $html;
        }
        $pattern = '~(<figure\b[^>]*\bclass=["\'][^"\']*\bmedia\b[^"\']*["\'][^>]*>\s*)?<oembed\b[^>]*\burl=(["\'])(.*?)\2[^>]*>(?:\s*</oembed>)?(\s*</figure>)?~is';
        return (string) preg_replace_callback($pattern, static function (array $match) use ($config): string {
            $url = trim(html_entity_decode($match[3], ENT_QUOTES));
            $figureOpen = $match[1];
            $figureClose = $match[4] ?? '';
            // Nur eine vollstaendige figure-Klammer beibehalten.
            if ('' === $figureOpen || '' === $figureClose) {
                $figureOpen = $figureClose = '';
            }
            $embed = self::embed($url, $config);
            if (null === $embed) {
                return $figureOpen . '<p><a href="' . rex_escape($url) . '" rel="noopener noreferrer">' . rex_escape($url) . '</a></p>' . $figureClose;
            }
            return $figureOpen . $embed . $figureClose;
        }, $html);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{src: string, service: string, title: string}|null
     */
    public static function resolve(string $url, array $config): ?array
    {
        if (1 !== preg_match('~^https?://~i', $url)) {
            return null;
        }
        $youtube = [
            '~youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)*v=|shorts/|embed/|live/)([a-zA-Z0-9_-]{6,})~',
            '~youtu\.be/([a-zA-Z0-9_-]{6,})~',
        ];
        foreach ($youtube as $pattern) {
            if (1 === preg_match($pattern, $url, $m)) {
                $src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
                if (1 === preg_match('~[?&](?:t|start)=(\d+)~', $url, $t)) {
                    $src .= '?start=' . $t[1];
                }
                return ['src' => $src, 'service' => Embed::serviceForUrl($src, $config) ?? 'youtube', 'title' => 'YouTube'];
            }
        }
        if (1 === preg_match('~vimeo\.com/(?:video/)?(\d{5,})(?:/([a-f0-9]+))?~', $url, $m)) {
            $src = 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1' . (isset($m[2]) ? '&h=' . $m[2] : '');
            return ['src' => $src, 'service' => Embed::serviceForUrl($src, $config) ?? 'vimeo', 'title' => 'Vimeo'];
        }
        // Unbekannter Anbieter: nur einbetten, wenn ein Dienst den Host kennt (z. B. Google Maps, Spotify).
        $service = Embed::serviceForUrl($url, $config);
        if (null === $service) {
            return null;
        }
        return ['src' => $url, 'service' => $service, 'title' => self::serviceName($service, $config)];
    }

    /** @param array<string, mixed> $config */
    public static function embed(string $url, array $config): ?string
    {
        $resolved = self::resolve($url, $config);
        if (null === $resolved) {
            return null;
        }
        $iframe = '<iframe src="' . rex_escape($resolved['src']) . '" title="' . rex_escape($resolved['title']) . '"'
            . ' loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"'
            . ' style="display:block;width:100%;aspect-ratio:16/9;border:0"></iframe>';
        return Embed::wrap($resolved['service'], $iframe, ['ratio' => '16/9']);
    }

    /** @param array<string, mixed> $config */
    private static function serviceName(string $key, array $config): string
    {
        foreach ($config['groups'] as $group) {
            foreach ($group['services'] as $service) {
                if ($service['key'] === $key) {
                    return (string) $service['name'];
                }
            }
        }
        return $key;
    }
}
