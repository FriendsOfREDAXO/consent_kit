<?php

namespace FriendsOfRedaxo\ConsentKit;

use rex_addon;
use rex_article;
use rex_clang;
use rex_path;
use rex_response;
use rex_url;

final class Frontend
{
    public const MARKER = 'id="consent-kit-config"';

    /**
     * Fruehes Inline-Script: Consent-Mode-Defaults und js_default der Dienste.
     * Muss vor allen Tags laufen, deshalb synchron und moeglichst weit oben im <head>.
     *
     * @param array<string, mixed> $config
     */
    public static function bootScript(array $config): string
    {
        if (!$config['gcm'] && [] === $config['jsDefault']) {
            return '';
        }
        $map = [];
        foreach ($config['groups'] as $group) {
            foreach ($group['services'] as $service) {
                if (!$group['required'] && [] !== $service['gcm']) {
                    $map[$service['key']] = ['h' => $service['h'], 'g' => $service['gcm']];
                }
            }
        }
        $boot = [
            'cookie' => $config['cookie'],
            'epoch' => $config['epoch'],
            'gcm' => $config['gcm'],
            'signals' => Repository::GCM_SIGNALS,
            'options' => $config['gcmOptions'],
            'map' => (object) $map,
        ];

        $js = '(function(c){var w=window,m=document.cookie.match(new RegExp("(?:^|; )"+c.cookie+"=([^;]*)")),s=null;'
            . 'try{s=m?JSON.parse(decodeURIComponent(m[1])):null}catch(e){}'
            . 'if(c.gcm){w.dataLayer=w.dataLayer||[];w.gtag=w.gtag||function(){w.dataLayer.push(arguments)};'
            . 'var d={},u={},k;c.signals.forEach(function(n){d[n]="denied"});d.security_storage="granted";'
            . 'if(c.options.waitForUpdate)d.wait_for_update=c.options.waitForUpdate;'
            . 'w.gtag("consent","default",d);'
            . 'if(c.options.adsDataRedaction)w.gtag("set","ads_data_redaction",true);'
            . 'if(c.options.urlPassthrough)w.gtag("set","url_passthrough",true);'
            . 'if(s&&s.e===c.epoch&&s.a){for(k in c.map){if(s.a[k]===c.map[k].h){c.map[k].g.forEach(function(n){u[n]="granted"})}}'
            . 'if(Object.keys(u).length)w.gtag("consent","update",u)}}'
            . '})(' . self::json($boot) . ');';

        foreach ($config['jsDefault'] as $snippet) {
            $js .= "\ntry{" . $snippet . "\n}catch(e){console.error('[consent-kit] js_default',e)}";
        }
        return '<script' . self::nonce() . '>' . str_ireplace('</script', '<\/script', $js) . '</script>';
    }

    /**
     * Konfiguration und Web Component.
     *
     * @param array<string, mixed> $config
     */
    public static function componentScripts(array $config): string
    {
        $addon = rex_addon::get('consent_kit');
        $domain = Consent::domain();
        $clangId = rex_clang::getCurrentId();

        $links = [];
        $quiet = false;
        foreach (['privacy_policy' => $domain['privacy_article_id'], 'imprint' => $domain['imprint_article_id']] as $textKey => $articleId) {
            if ($articleId > 0 && null !== rex_article::get($articleId, $clangId)) {
                $links[] = ['label' => $config['texts'][$textKey], 'url' => rex_getUrl($articleId, $clangId)];
                $quiet = $quiet || rex_article::getCurrentId() === $articleId;
            }
        }

        $public = $config;
        unset($public['jsDefault'], $public['gcmOptions']);
        $public['links'] = $links;
        // Datenschutz und Impressum muessen ohne Entscheidung lesbar bleiben.
        $public['quiet'] = $quiet;
        $public['endpoint'] = rex_url::base('index.php') . '?rex-api-call=consent_kit';
        $public['cssVars'] = (object) array_filter((array) $addon->getConfig('css_vars', []), 'is_string');
        $public['cssUrl'] = self::styleUrl();

        $file = $addon->getAssetsPath('consent-kit.js');
        $version = $addon->getVersion() . '-' . (is_file($file) ? filemtime($file) : 0);

        return '<script type="application/json" ' . self::MARKER . '>' . self::json($public) . '</script>'
            . '<script type="module" src="' . rex_url::addonAssets('consent_kit', 'consent-kit.js') . '?v=' . rawurlencode($version) . '"></script>';
    }

    /**
     * Eigenes Stylesheet fuer das Shadow DOM: absolute URL oder projektinterner Pfad.
     * Leer, wenn nichts gesetzt oder der Wert keine brauchbare URL ergibt.
     */
    public static function styleUrl(): string
    {
        $value = trim((string) rex_addon::get('consent_kit')->getConfig('css_url', ''));
        if ('' === $value) {
            return '';
        }
        if (1 === preg_match('~^https?://~i', $value)) {
            return false !== filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
        }
        /*
         * Projektinterner Pfad: absolut ausgeben, denn rex_url::base() liefert einen relativen
         * Pfad ("../assets/…"), der auf Unterseiten ins Leere zeigen wuerde. Das Basisverzeichnis
         * stammt aus rex_url::frontendController(), damit auch Installationen in einem
         * Unterverzeichnis den richtigen Praefix bekommen.
         */
        $controller = rex_url::frontendController();
        $prefix = rtrim(str_replace('\\', '/', dirname('/' . ltrim(preg_replace('~^(\.\./)+~', '', $controller) ?? '', '/'))), '/');
        $path = ltrim($value, '/');
        $url = $prefix . '/' . $path;

        /*
         * Cache-Buster wie beim eigenen Script: Ohne ihn saehen Besucher nach einer Aenderung
         * die alte Datei aus dem Browser-Cache. Ein selbst gesetzter Query-String bleibt unberuehrt.
         */
        if (!str_contains($path, '?') && !str_contains($path, '#')) {
            $file = rex_path::frontend($path);
            if (is_file($file)) {
                $url .= '?v=' . filemtime($file);
            }
        }

        return $url;
    }

    public static function head(): string
    {
        $config = Consent::config();
        return self::bootScript($config) . self::componentScripts($config);
    }

    /** OUTPUT_FILTER: Boot-Script nach oben, Rest vor </head>, optional iframes blocken. */
    public static function inject(string $html): string
    {
        if (false === stripos($html, '</head>')) {
            return $html;
        }
        $addon = rex_addon::get('consent_kit');
        $config = Consent::config();

        if (!str_contains($html, self::MARKER) && $addon->getConfig('auto_inject', true)) {
            $boot = self::bootScript($config);
            // Hinter <meta charset>, damit die Zeichensatz-Angabe in den ersten 1024 Bytes bleibt.
            $count = 0;
            $html = (string) preg_replace('~(<head\b[^>]*>(?:\s*<meta\b[^>]*charset[^>]*>)?)~i', '$1' . addcslashes($boot, '\\$'), $html, 1, $count);
            $rest = self::componentScripts($config);
            if (0 === $count) {
                $rest = $boot . $rest;
            }
            $position = stripos($html, '</head>');
            if (false !== $position) {
                $html = substr_replace($html, $rest, $position, 0);
            }
        }

        if ($addon->getConfig('oembed', true)) {
            $html = Oembed::render($html, $config);
        }
        if ($addon->getConfig('block_embeds', false)) {
            $html = Embed::filter($html, $config);
        }
        return $html;
    }

    public static function json(mixed $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    private static function nonce(): string
    {
        return ' nonce="' . rex_response::getNonce() . '"';
    }
}
