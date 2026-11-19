<?php

use FriendsOfRedaxo\ConsentKit\Cache;
use FriendsOfRedaxo\ConsentKit\Frontend;
use FriendsOfRedaxo\ConsentKit\Repository;

$addon = rex_addon::get('consent_kit');
$csrf = rex_csrf_token::factory('consent_kit');

/*
 * Gestaltbare Variablen: [Typ, Standard hell, Standard dunkel|null, Ausgangsvariable|null].
 * Dunkle Werte heissen --ck-dark-*, siehe assets/consent-kit.js. Mit Ausgangsvariable folgt
 * der Wert ihr, bis er davon abweicht - nur dann wird er gespeichert.
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
    'button-hover-bg' => ['color', '#1f2937', '#f4f4f5', 'button-bg'],
    'button-hover-text' => ['color', '#ffffff', '#18181b', 'button-text'],
    'button-hover-border' => ['color', '#1f2937', '#f4f4f5', 'button-border'],
    'trigger-bg' => ['color', '#ffffff', '#18181b', 'bg'],
    'trigger-border' => ['color', '#8c8c8c', '#8e8e96', 'border'],
    'trigger-text' => ['color', '#1a1a1a', '#f4f4f5', 'text'],
    'trigger-icon-fill' => ['color', '#ffffff', '#18181b', 'trigger-bg'],
    'trigger-hover-bg' => ['color', '#ffffff', '#18181b', 'trigger-bg'],
    'trigger-hover-text' => ['color', '#1a1a1a', '#f4f4f5', 'trigger-text'],
    'trigger-hover-border' => ['color', '#8c8c8c', '#8e8e96', 'trigger-border'],
    'button-hover-filter' => ['filter', 'auto', null],
    'trigger-size' => ['length', '2.75rem', null],
    'trigger-icon-size' => ['length', '1.4rem', null],
    'trigger-radius' => ['length', '50%', null],
    'trigger-shadow' => ['shadow', '0 2px 10px rgba(0, 0, 0, .18)', null],
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

$prefixOf = static fn (string $name): string => str_starts_with($name, '--ck-dark-') ? '--ck-dark-' : '--ck-';
$defaultOf = static function (string $name) use ($variables, $prefixOf): ?string {
    $prefix = $prefixOf($name);
    $definition = $variables[substr($name, strlen($prefix))] ?? null;
    if (null === $definition) {
        return null;
    }
    return '--ck-dark-' === $prefix ? $definition[2] : $definition[1];
};
/** Wirksamer Wert: eigener Wert, sonst der der Ausgangsvariable, sonst der Standard. */
$resolve = static function (string $name, array $values) use (&$resolve, $variables, $prefixOf, $defaultOf): ?string {
    if (isset($values[$name])) {
        return (string) $values[$name];
    }
    $prefix = $prefixOf($name);
    $parent = $variables[substr($name, strlen($prefix))][3] ?? null;
    return null !== $parent ? $resolve($prefix . $parent, $values) : $defaultOf($name);
};

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
    $config['links'] = [['key' => 'privacy', 'label' => $config['texts']['privacy_policy'], 'url' => '#'], ['key' => 'imprint', 'label' => $config['texts']['imprint'], 'url' => '#']];
    // Variablen kommen live per postMessage; das eigene Stylesheet wird wie im Frontend geladen.
    $config['cssVars'] = (object) [];
    $config['cssUrl'] = Frontend::styleUrl();
    $config['triggerIcon'] = Frontend::triggerIconUrl();
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
        . 'all.forEach(function(el){el.setAttribute("theme",d.theme);if(d.triggerStyle)el.setAttribute("trigger-style",d.triggerStyle);if(typeof d.triggerIcon==="string"&&el.setTriggerIcon)el.setTriggerIcon(d.triggerIcon);[].slice.call(el.style).forEach(function(p){el.style.removeProperty(p)});Object.keys(d.vars).forEach(function(n){el.style.setProperty(n,d.vars[n])});if(el.syncDesign)el.syncDesign()});'
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
        $addon->setConfig('trigger_style', 'circle');
        $addon->setConfig('trigger_icon', '');
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

        $posted = [];
        foreach (rex_request::post('vars', 'array', []) as $name => $value) {
            $value = trim((string) $value);
            $base = preg_replace('~^--ck-(dark-)?~', '', (string) $name);
            if (!isset($variables[$base]) || '' === $value) {
                continue;
            }
            $valid = match ($variables[$base][0]) {
                'color' => 1 === preg_match('~^#[0-9a-f]{6}$~i', $value),
                'length' => 1 === preg_match('~^\d*\.?\d+(px|rem|em|%)$~', $value),
                'number' => 1 === preg_match('~^\d*\.?\d+$~', $value),
                'filter' => 1 === preg_match('~^(auto|none|((brightness|contrast|saturate|opacity)\(\d*\.?\d+%?\)\s*)+)$~', $value),
                'shadow' => 1 === preg_match('~^(none|[\d\s.,%#a-z()-]{1,200})$~i', $value) && !str_contains(strtolower($value), 'url('),
                // Schriftart, Innenabstand, Versalien: mehrteilige Werte, aber keine Funktionen oder Semikola.
                default => 1 === preg_match('~^[\w\s,"\'.%-]{1,200}$~u', $value),
            };
            if ($valid) {
                $posted[(string) $name] = $value;
            }
        }
        // Nur Abweichungen speichern: vom Standard oder, bei abgeleiteten Werten, von der Ausgangsvariable.
        $saved = [];
        foreach ($posted as $name => $value) {
            $prefix = $prefixOf($name);
            $parent = $variables[substr($name, strlen($prefix))][3] ?? null;
            $reference = null !== $parent ? $resolve($prefix . $parent, $posted) : $defaultOf($name);
            if (null !== $reference && strtolower($value) !== strtolower($reference)) {
                $saved[$name] = $value;
            }
        }
        $addon->setConfig('css_vars', $saved);
        $addon->setConfig('trigger_style', 'cookie' === rex_request::post('trigger_style', 'string', 'circle') ? 'cookie' : 'circle');
        $icon = trim(rex_request::post('trigger_icon', 'string', ''));
        if ('' === $icon) {
            $addon->setConfig('trigger_icon', '');
        } elseif (null !== ($media = rex_media::get($icon)) && 'svg' === strtolower($media->getExtension())) {
            $addon->setConfig('trigger_icon', $icon);
        } else {
            $cssError = trim($cssError . ' ' . rex_i18n::msg('consent_kit_trigger_icon_invalid'));
        }
        Cache::clear();
        $message = '' === $cssError
            ? rex_view::success(rex_i18n::msg('consent_kit_design_saved'))
            : rex_view::warning($cssError);
    }
}

$saved = (array) $addon->getConfig('css_vars', []);
$field = static function (string $name, string $type, string $default) use ($saved, $variables, $prefixOf, $resolve): string {
    $prefix = $prefixOf($name);
    $base = substr($name, strlen($prefix));
    $parent = $variables[$base][3] ?? null;
    // Abgeleitete Werte zeigen, was tatsaechlich gilt - nicht den festen Standard.
    $value = (string) ($resolve($name, $saved) ?? $default);
    $label = rex_i18n::msg('consent_kit_var_' . str_replace('-', '_', $base));
    $id = 'ck-var-' . substr($name, 5);
    $data = 'data-ck-var="' . $name . '" data-default="' . rex_escape($default) . '"' . (null !== $parent ? ' data-parent="' . $prefix . $parent . '"' : '');
    if ('color' === $type) {
        return '<div class="ck-var"><label for="' . $id . '">' . $label . '</label><span class="ck-color"><input type="color" id="' . $id . '" name="vars[' . $name . ']" value="' . rex_escape($value) . '" ' . $data . '><output for="' . $id . '">' . rex_escape($value) . '</output></span></div>';
    }
    return '<div class="ck-var"><label for="' . $id . '">' . $label . '</label><input type="text" class="form-control" id="' . $id . '" name="vars[' . $name . ']" value="' . rex_escape($value) . '" ' . $data . ' spellcheck="false"></div>';
};

/* Nicht-Farbwerte nach Thema gruppiert, sonst wird die Liste unuebersichtlich. */
$sections = [
    'typo' => ['font', 'rem', 'font-size', 'line-height', 'heading-size', 'heading-weight', 'small-size'],
    'spacing' => ['space', 'gap', 'width', 'settings-width'],
    'shape' => ['radius', 'button-radius', 'group-radius', 'border-width', 'switch-width', 'switch-height'],
    'buttons' => ['button-padding', 'button-weight', 'button-border-width', 'button-transform', 'button-hover-filter'],
    'trigger' => ['trigger-size', 'trigger-icon-size', 'trigger-radius', 'trigger-shadow'],
];

/* Felder, die nur den Kreis um den Keks gestalten - bei "Nur Keks" ausgeblendet. */
$circleOnly = static function (string $base, string $html): string {
    if (!in_array($base, ['trigger-bg', 'trigger-border', 'trigger-hover-bg', 'trigger-hover-border', 'trigger-radius', 'trigger-shadow'], true)) {
        return $html;
    }
    return '<div class="ck-vars-group" data-ck-show-if="trigger_style" data-ck-show-values="circle">' . $html . '</div>';
};
$triggerStyle = 'cookie' === $addon->getConfig('trigger_style', 'circle') ? 'cookie' : 'circle';
$triggerStyleField = '<div class="ck-var ck-vars-wide"><label for="ck-trigger-style">' . rex_i18n::msg('consent_kit_trigger_style') . '</label>'
    . '<select class="form-control" id="ck-trigger-style" name="trigger_style" data-ck-trigger-style>'
    . '<option value="circle"' . ('circle' === $triggerStyle ? ' selected' : '') . '>' . rex_i18n::msg('consent_kit_trigger_style_circle') . '</option>'
    . '<option value="cookie"' . ('cookie' === $triggerStyle ? ' selected' : '') . '>' . rex_i18n::msg('consent_kit_trigger_style_cookie') . '</option>'
    . '</select></div>';

$triggerIconField = '';
if (rex_addon::get('mediapool')->isAvailable()) {
    // Medien-URL ohne Dateiname, damit die Vorschau eine neue Auswahl sofort zeigen kann.
    $mediaBase = substr(rex_url::media('x'), 0, -1);
    $triggerIconField = '<div class="ck-var ck-vars-wide" data-ck-trigger-icon data-media-base="' . rex_escape($mediaBase) . '"><label for="REX_MEDIA_9100">' . rex_i18n::msg('consent_kit_trigger_icon') . '</label>'
        . rex_var_media::getWidget(9100, 'trigger_icon', (string) $addon->getConfig('trigger_icon', ''), ['types' => 'svg'])
        . '<p class="help-block">' . rex_i18n::msg('consent_kit_trigger_icon_help') . '</p></div>';
}

$light = $dark = $triggerLight = $triggerDark = '';
$groups = array_fill_keys(array_keys($sections), '');
foreach ($variables as $base => [$type, $lightDefault, $darkDefault]) {
    if ('color' === $type) {
        $lightField = $field('--ck-' . $base, $type, $lightDefault);
        $darkField = $field('--ck-dark-' . $base, $type, (string) $darkDefault);
        if (str_starts_with($base, 'trigger-')) {
            $triggerLight .= $circleOnly($base, $lightField);
            $triggerDark .= $circleOnly($base, $darkField);
        } else {
            $light .= $lightField;
            $dark .= $darkField;
        }
        continue;
    }
    foreach ($sections as $section => $keys) {
        if (in_array($base, $keys, true)) {
            $groups[$section] .= $circleOnly($base, $field('--ck-' . $base, $type, $lightDefault));
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
    . '<p class="help-block">' . rex_i18n::msg('consent_kit_design_equal_buttons') . ' ' . rex_i18n::rawMsg('consent_kit_design_hover_filter_help') . '</p>'
    . '<fieldset class="ck-vars"><legend>' . rex_i18n::msg('consent_kit_design_trigger') . '</legend>'
    . '<p class="help-block ck-vars-wide">' . rex_i18n::msg('consent_kit_design_trigger_help') . '</p>'
    . $triggerStyleField
    . $triggerIconField
    . '<h4 class="ck-vars-wide">' . rex_i18n::msg('consent_kit_design_light') . '</h4>' . $triggerLight
    . '<h4 class="ck-vars-wide">' . rex_i18n::msg('consent_kit_design_dark') . '</h4>' . $triggerDark
    . $groups['trigger'] . '</fieldset>'
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
