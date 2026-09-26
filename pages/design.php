<?php

use FriendsOfRedaxo\ConsentKit\Cache;
use FriendsOfRedaxo\ConsentKit\Frontend;
use FriendsOfRedaxo\ConsentKit\Repository;

$addon = rex_addon::get('consent_kit');
$csrf = rex_csrf_token::factory('consent_kit');

/*
 * Gestaltbare Variablen: [Typ, Standard hell, Standard dunkel|null].
 * Dunkle Werte heissen --ck-dark-*, siehe assets/consent-kit.js.
 */
$variables = [
    'bg' => ['color', '#ffffff', '#18181b'],
    'text' => ['color', '#1a1a1a', '#f4f4f5'],
    'muted' => ['color', '#595959', '#b4b4bb'],
    'border' => ['color', '#8c8c8c', '#8e8e96'],
    'line' => ['color', '#d9d9d9', '#3f3f46'],
    'accent' => ['color', '#1d4ed8', '#93c5fd'],
    'button-bg' => ['color', '#1f2937', '#f4f4f5'],
    'button-text' => ['color', '#ffffff', '#18181b'],
    'button-border' => ['color', '#1f2937', '#f4f4f5'],
    'button-hover-bg' => ['color', '#1f2937', '#f4f4f5'],
    'button-hover-text' => ['color', '#ffffff', '#18181b'],
    'button-hover-border' => ['color', '#1f2937', '#f4f4f5'],
    'radius' => ['length', '12px', null],
    'button-radius' => ['length', '8px', null],
    'group-radius' => ['length', '10px', null],
    'border-width' => ['length', '1px', null],
    'button-border-width' => ['length', '2px', null],
    'space' => ['length', '1.25rem', null],
    'gap' => ['length', '0.6rem', null],
    'rem' => ['length', '1rem', null],
    'font-size' => ['length', '1rem', null],
    'line-height' => ['number', '1.5', null],
    'heading-size' => ['length', '1.2em', null],
    'heading-weight' => ['number', '700', null],
    'small-size' => ['length', '0.875em', null],
    'button-weight' => ['number', '600', null],
    'switch-width' => ['length', '2.75rem', null],
    'switch-height' => ['length', '1.5rem', null],
    'width' => ['length', '30rem', null],
    'settings-width' => ['length', '44rem', null],
    'font' => ['text', 'inherit', null],
    'button-padding' => ['text', '.55rem 1rem', null],
    'button-transform' => ['text', 'none', null],
];

// Vorschau-Dokument fuer das iframe: echte Komponente, echte Dienste, aber ohne Cookie und Protokoll.
if (rex_request::get('preview', 'bool', false)) {
    rex_response::cleanOutputBuffers();
    $domain = Repository::domains()[0] ?? ['id' => 0, 'host' => '*', 'privacy_article_id' => 0, 'imprint_article_id' => 0];
    $clang = rex_clang::get(rex_clang::getStartId());
    $config = Cache::build($domain, null !== $clang ? $clang->getCode() : 'de');
    unset($config['jsDefault'], $config['gcmOptions']);
    $config['gcm'] = false;
    $config['preview'] = true;
    $config['trigger'] = true;
    $config['dismiss'] = (bool) $addon->getConfig('dismissible', true);
    $config['quiet'] = false;
    $config['endpoint'] = '';
    $config['links'] = [['label' => $config['texts']['privacy_policy'], 'url' => '#'], ['label' => $config['texts']['imprint'], 'url' => '#']];
    // Variablen kommen live per postMessage; das eigene Stylesheet wird wie im Frontend geladen.
    $config['cssVars'] = (object) [];
    $config['cssUrl'] = Frontend::styleUrl();
    foreach ($config['groups'] as &$group) {
        foreach ($group['services'] as &$service) {
            $service['head'] = $service['body'] = $service['jsAccept'] = $service['jsRevoke'] = '';
        }
    }
    unset($group, $service);
    $embedService = '';
    foreach ($config['groups'] as $group) {
        if (!$group['required'] && isset($group['services'][0])) {
            $embedService = $group['services'][0]['key'];
        }
    }

    rex_response::sendContent('<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Preview</title>'
        . '<style>html{font-family:system-ui,sans-serif;line-height:1.5}body{margin:0;padding:2rem;max-width:60rem;margin-inline:auto;transition:background .2s,color .2s}'
        . 'body.dark{background:#0f0f11;color:#e4e4e7}.ph{height:.8rem;margin:.7rem 0;border-radius:4px;background:currentColor;opacity:.12}.ph.w60{width:60%}.ph.w80{width:80%}h1{font-size:1.6rem}</style>'
        . '<script type="application/json" ' . Frontend::MARKER . '>' . Frontend::json($config) . '</script>'
        . '<script type="module" src="' . $addon->getAssetsUrl('consent-kit.js') . '?v=' . time() . '"></script></head><body>'
        . '<h1>' . rex_escape(rex::getServerName()) . '</h1><div class="ph"></div><div class="ph w80"></div><div class="ph w60"></div>'
        . '<div id="embed-demo" hidden style="margin-top:1.5rem">' . ('' !== $embedService ? '<consent-embed service="' . rex_escape($embedService) . '"><template><p>Embed</p></template></consent-embed>' : '') . '</div>'
        . '<div class="ph"></div><div class="ph w80"></div><div class="ph"></div><div class="ph w60"></div>'
        . '<script>window.addEventListener("message",function(e){if(e.origin!==location.origin||!e.data||e.data.source!=="consent-kit-design")return;var d=e.data,k=document.querySelector("consent-kit"),all=document.querySelectorAll("consent-kit,consent-embed");'
        . 'all.forEach(function(el){el.setAttribute("theme",d.theme);[].slice.call(el.style).forEach(function(p){el.style.removeProperty(p)});Object.keys(d.vars).forEach(function(n){el.style.setProperty(n,d.vars[n])})});'
        . 'document.body.classList.toggle("dark",d.theme==="dark");document.getElementById("embed-demo").hidden=d.view!=="embed";'
        . 'if(!k)return;k.setAttribute("layout",d.layout);k.setAttribute("position",d.position);if(d.view==="embed"){k.close()}else{k.open(d.view)}});'
        . 'document.addEventListener("consentkit:ready",function(){parent.postMessage({source:"consent-kit-preview"},location.origin)});</script>'
        . '</body></html>', 'text/html');
    exit;
}

$message = '';
if ('post' === rex_request::requestMethod()) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif (rex_request::post('reset', 'bool', false)) {
        $addon->setConfig('css_vars', []);
        $addon->setConfig('css_url', '');
        Cache::clear();
        $message = rex_view::success(rex_i18n::msg('consent_kit_design_reset_done'));
    } else {
        // Eigenes Stylesheet: absolute http(s)-URL oder projektinterner Pfad.
        $cssUrl = trim(rex_request::post('css_url', 'string', ''));
        $cssError = '';
        if ('' !== $cssUrl) {
            $isAbsolute = 1 === preg_match('~^https?://~i', $cssUrl);
            $ok = $isAbsolute
                ? false !== filter_var($cssUrl, FILTER_VALIDATE_URL)
                : 1 === preg_match('~^/?[\w./-]+\.css([?#][\w.=&%-]*)?$~', $cssUrl);
            if ($ok) {
                $addon->setConfig('css_url', $cssUrl);
            } else {
                $cssError = rex_i18n::msg('consent_kit_design_css_url_invalid');
            }
        } else {
            $addon->setConfig('css_url', '');
        }

        $saved = [];
        foreach (rex_request::post('vars', 'array', []) as $name => $value) {
            $value = trim((string) $value);
            $base = preg_replace('~^--ck-(dark-)?~', '', (string) $name);
            if (!isset($variables[$base]) || '' === $value) {
                continue;
            }
            [$type, $light, $dark] = $variables[$base];
            $default = str_starts_with((string) $name, '--ck-dark-') ? $dark : $light;
            $valid = match ($type) {
                'color' => 1 === preg_match('~^#[0-9a-f]{6}$~i', $value),
                'length' => 1 === preg_match('~^\d*\.?\d+(px|rem|em|%)$~', $value),
                'number' => 1 === preg_match('~^\d*\.?\d+$~', $value),
                // Schriftart, Innenabstand, Versalien: mehrteilige Werte, aber keine Funktionen oder Semikola.
                default => 1 === preg_match('~^[\w\s,"\'.%-]{1,200}$~u', $value),
            };
            // Nur Abweichungen vom Standard speichern.
            if ($valid && null !== $default && strtolower($value) !== strtolower($default)) {
                $saved[(string) $name] = $value;
            }
        }
        $addon->setConfig('css_vars', $saved);
        Cache::clear();
        $message = '' === $cssError
            ? rex_view::success(rex_i18n::msg('consent_kit_design_saved'))
            : rex_view::warning($cssError);
    }
}

$saved = (array) $addon->getConfig('css_vars', []);
$field = static function (string $name, string $type, string $default) use ($saved): string {
    $value = (string) ($saved[$name] ?? $default);
    $label = rex_i18n::msg('consent_kit_var_' . str_replace('-', '_', (string) preg_replace('~^--ck-(dark-)?~', '', $name)));
    $id = 'ck-var-' . substr($name, 5);
    if ('color' === $type) {
        return '<div class="ck-var"><label for="' . $id . '">' . $label . '</label><span class="ck-color"><input type="color" id="' . $id . '" name="vars[' . $name . ']" value="' . rex_escape($value) . '" data-ck-var="' . $name . '" data-default="' . $default . '"><output for="' . $id . '">' . rex_escape($value) . '</output></span></div>';
    }
    return '<div class="ck-var"><label for="' . $id . '">' . $label . '</label><input type="text" class="form-control" id="' . $id . '" name="vars[' . $name . ']" value="' . rex_escape($value) . '" data-ck-var="' . $name . '" data-default="' . rex_escape($default) . '" spellcheck="false"></div>';
};

/* Nicht-Farbwerte nach Thema gruppiert, sonst wird die Liste unuebersichtlich. */
$sections = [
    'typo' => ['font', 'rem', 'font-size', 'line-height', 'heading-size', 'heading-weight', 'small-size'],
    'spacing' => ['space', 'gap', 'width', 'settings-width'],
    'shape' => ['radius', 'button-radius', 'group-radius', 'border-width', 'switch-width', 'switch-height'],
    'buttons' => ['button-padding', 'button-weight', 'button-border-width', 'button-transform'],
];

$light = $dark = '';
$groups = array_fill_keys(array_keys($sections), '');
foreach ($variables as $base => [$type, $lightDefault, $darkDefault]) {
    if ('color' === $type) {
        $light .= $field('--ck-' . $base, $type, $lightDefault);
        $dark .= $field('--ck-dark-' . $base, $type, (string) $darkDefault);
        continue;
    }
    foreach ($sections as $section => $keys) {
        if (in_array($base, $keys, true)) {
            $groups[$section] .= $field('--ck-' . $base, $type, $lightDefault);
            break;
        }
    }
}

$toggle = static function (string $name, array $options, string $current): string {
    $out = '<div class="btn-group ck-toggle" role="group" aria-label="' . rex_i18n::msg('consent_kit_preview_' . $name) . '">';
    foreach ($options as $value => $label) {
        $out .= '<button type="button" class="btn btn-default btn-sm' . ($value === $current ? ' active' : '') . '" data-ck-preview="' . $name . '" data-value="' . $value . '" aria-pressed="' . ($value === $current ? 'true' : 'false') . '">' . $label . '</button>';
    }
    return $out . '</div>';
};

$controls = '<form method="post" action="' . rex_url::currentBackendPage() . '" class="ck-form" data-ck-design>' . $csrf->getHiddenField()
    . '<div class="ck-contrast" role="status" aria-live="polite" data-ck-contrast data-label-ok="' . rex_i18n::msg('consent_kit_contrast_ok') . '" data-label-fail="' . rex_i18n::msg('consent_kit_contrast_fail') . '"></div>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_light') . '</legend>' . $light . '</fieldset>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_dark') . '</legend>' . $dark . '</fieldset>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_typo') . '</legend>' . $groups['typo'] . '</fieldset>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_spacing') . '</legend>' . $groups['spacing'] . '</fieldset>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_shape') . '</legend>' . $groups['shape'] . '</fieldset>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_buttons') . '</legend>' . $groups['buttons'] . '</fieldset>'
    . '<p class="help-block">' . rex_i18n::msg('consent_kit_design_equal_buttons') . '</p>'
    . '<fieldset class="ck-stylesheet"><legend>' . rex_i18n::msg('consent_kit_design_css_url') . '</legend>'
    . '<div class="ck-var ck-var-wide"><label for="ck-css-url">' . rex_i18n::msg('consent_kit_design_css_url_label') . '</label>'
    . '<input type="text" class="form-control" id="ck-css-url" name="css_url" value="' . rex_escape((string) $addon->getConfig('css_url', '')) . '" placeholder="/assets/consent-kit.css" spellcheck="false"></div>'
    . '<p class="help-block">' . rex_i18n::rawMsg('consent_kit_design_css_url_help') . '</p></fieldset>'
    . '<footer class="ck-form-footer"><button type="submit" class="btn btn-save">' . rex_i18n::msg('consent_kit_save') . '</button> '
    . '<button type="submit" class="btn btn-default" name="reset" value="1" data-confirm="' . rex_i18n::msg('consent_kit_design_reset_confirm') . '">' . rex_i18n::msg('consent_kit_design_reset') . '</button></footer></form>'
    . '<details class="ck-css-export"><summary>' . rex_i18n::msg('consent_kit_design_css') . '</summary><p class="help-block">' . rex_i18n::msg('consent_kit_design_css_help') . '</p><pre><code data-ck-css></code></pre></details>';

/*
 * Der Layout-Umschalter probiert die Formen nur in der Vorschau durch; gespeichert wird
 * die Form weiterhin unter Einstellungen. Startwert ist deshalb der gespeicherte Wert.
 */
$previewLayout = (string) $addon->getConfig('layout', 'box');
$preview = '<div class="ck-preview-tools">'
    . $toggle('view', ['banner' => rex_i18n::msg('consent_kit_preview_banner'), 'settings' => rex_i18n::msg('consent_kit_preview_settings'), 'embed' => rex_i18n::msg('consent_kit_preview_embed')], 'banner')
    . $toggle('layout', [
        'box' => rex_i18n::msg('consent_kit_layout_box'),
        'bar' => rex_i18n::msg('consent_kit_layout_bar'),
        'modal' => rex_i18n::msg('consent_kit_layout_modal'),
        'offcanvas' => rex_i18n::msg('consent_kit_layout_offcanvas'),
    ], in_array($previewLayout, ['box', 'bar', 'modal', 'offcanvas'], true) ? $previewLayout : 'box')
    . $toggle('theme', ['light' => rex_i18n::msg('consent_kit_theme_light'), 'dark' => rex_i18n::msg('consent_kit_theme_dark')], 'dark' === $addon->getConfig('theme') ? 'dark' : 'light')
    . $toggle('size', ['desktop' => rex_i18n::msg('consent_kit_preview_desktop'), 'mobile' => rex_i18n::msg('consent_kit_preview_mobile')], 'desktop')
    . '</div><div class="ck-preview-frame" data-size="desktop"><iframe title="' . rex_i18n::msg('consent_kit_preview') . '" src="' . rex_url::currentBackendPage(['preview' => 1]) . '" data-ck-preview-frame data-layout="' . rex_escape((string) $addon->getConfig('layout', 'box')) . '" data-position="' . rex_escape((string) $addon->getConfig('position', 'bottom-left')) . '"></iframe></div>';

echo $message;
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('consent_kit_design'), false);
$fragment->setVar('body', '<div class="ck-design"><div class="ck-design-controls">' . $controls . '</div><div class="ck-design-preview">' . $preview . '</div></div>', false);
echo $fragment->parse('core/page/section.php');
