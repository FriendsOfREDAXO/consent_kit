<?php

$addon = rex_addon::get('consent_kit');

// Kapitel in Lesereihenfolge; README.md ist die Startseite.
$chapters = [
    'start' => ['README.md', 'Überblick'],
    'einrichtung' => ['docs/01_einrichtung.md', 'Einrichtung'],
    'dienste' => ['docs/02_dienste.md', 'Dienste, Domains, Varianten'],
    'einbindung' => ['docs/03_einbindung.md', 'Einbindung im Template'],
    'design' => ['docs/04_design.md', 'Gestaltung'],
    'signale' => ['docs/05_signale.md', 'Consent Mode und Signale'],
    'protokoll' => ['docs/06_protokoll.md', 'Protokoll und Nachweis'],
    'api' => ['docs/07_api.md', 'API-Referenz'],
    'tipps' => ['docs/08_tipps.md', 'Tipps und Tricks'],
    'umstieg' => ['docs/09_umstieg.md', 'Umstieg vom consent_manager'],
];

$current = rex_request::get('doc', 'string', 'start');
if (!isset($chapters[$current])) {
    $current = 'start';
}

$nav = '';
foreach ($chapters as $key => [, $title]) {
    $nav .= '<li><a href="' . rex_url::currentBackendPage(['doc' => $key]) . '"' . ($key === $current ? ' aria-current="page"' : '') . '>' . rex_escape($title) . '</a></li>';
}

$markdown = (string) rex_file::get($addon->getPath($chapters[$current][0]));
$html = rex_markdown::factory()->parse($markdown);

// Verweise zwischen den Kapiteln (…/02_dienste.md#anker) auf die Hilfe-Seite umbiegen.
$files = [];
foreach ($chapters as $key => [$file]) {
    $files[basename($file)] = $key;
}
$html = (string) preg_replace_callback('~href="(?:docs/|\./)?([\w.-]+\.md)(#[^"]*)?"~', static function (array $match) use ($files): string {
    if (!isset($files[$match[1]])) {
        return $match[0];
    }
    return 'href="' . rex_url::currentBackendPage(['doc' => $files[$match[1]]]) . ($match[2] ?? '') . '"';
}, $html);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('consent_kit_help') . ': ' . rex_escape($chapters[$current][1]), false);
$fragment->setVar('body', '<div class="ck-help"><nav class="ck-help-nav" aria-label="' . rex_i18n::msg('consent_kit_help_nav') . '"><ul>' . $nav . '</ul></nav><article class="ck-help-content">' . $html . '</article></div>', false);
echo $fragment->parse('core/page/section.php');
