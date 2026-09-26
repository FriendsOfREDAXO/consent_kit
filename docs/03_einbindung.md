# Einbindung im Template

## Automatisch

Standard. Ein `OUTPUT_FILTER` fügt im Frontend ein:

1. direkt nach `<meta charset>` ein kleines Inline-Script mit den Consent-Mode-Defaults und dem „JavaScript vor jeder Entscheidung“ der Dienste – nur wenn es etwas zu setzen gibt,
2. vor `</head>` die Konfiguration als JSON und die Web Component als ES-Modul.

Das Element `<consent-kit>` hängt sich selbst an den Anfang des `<body>`, damit es in der Tab-Reihenfolge zuerst kommt.

## Manuelle Einbindung

Automatik unter **Einstellungen → Darstellung** abschalten und im Template möglichst weit oben im `<head>`:

```html
<head>
    <meta charset="utf-8">
    REX_CONSENT_KIT[]
    …
</head>
```

Dasselbe in PHP, etwa in einem Template mit `<?php`-Blöcken oder in einem eigenen Layout:

```php
<?php echo \FriendsOfRedaxo\ConsentKit\Consent::head(); ?>
```

Steht die Ausgabe bereits im HTML, fügt die Automatik nichts doppelt ein. Wer Layout oder Farbschema für ein Template abweichend setzen will, platziert das Element selbst:

```html
<consent-kit layout="bar" position="top-left" theme="auto"></consent-kit>
<consent-kit layout="offcanvas" position="top-right"></consent-kit>
```

## Eigene Scripts sperren

Scripts, die im Backend beim Dienst hinterlegt sind, brauchen nichts weiter. Für Scripts im Template oder in Modulen:

```html
<!-- Inline -->
<script type="text/plain" data-consent="matomo">
    _paq.push(['trackEvent', 'Video', 'Play']);
</script>

<!-- Extern -->
<script type="text/plain" data-consent="matomo" data-src="https://stats.example.org/custom.js"></script>

<!-- ES-Modul -->
<script type="text/plain" data-type="module" data-consent="maps" data-src="/assets/map.js"></script>
```

`data-consent` ist der Schlüssel des Dienstes. Die Scripts werden aktiviert, sobald die Einwilligung vorliegt – auch bei Inhalten, die später per AJAX eingefügt werden.

## Serverseitig prüfen

```php
use FriendsOfRedaxo\ConsentKit\Consent;

if (Consent::has('matomo')) {
    echo '<img src="https://stats.example.org/matomo.php?idsite=3&rec=1" alt="">';
}
```

`Consent::has()` liest den Cookie des aktuellen Requests. Direkt nach dem Klick auf „Akzeptieren“ stimmt das Ergebnis also erst beim nächsten Seitenaufruf, und hinter einem Full-Page-Cache ist es unbrauchbar. Für sichtbare Inhalte ist der Platzhalter die bessere Wahl.

## Externe Inhalte

```php
echo Consent::embed(
    'youtube',
    '<iframe src="https://www.youtube-nocookie.com/embed/…" title="Imagefilm" allowfullscreen></iframe>',
    ['title' => 'Imagefilm', 'ratio' => '16/9']
);
```

Das Original-Markup liegt inert in einem `<template>` und gelangt erst nach Einwilligung in die Seite. Der Platzhalter bietet drei gleich gestaltete Schaltflächen: „Inhalt einmal laden“ (ohne Speicherung), „… immer erlauben“ (speichert die Einwilligung für den Dienst) und „Cookie-Einstellungen öffnen“.

Im Text des Platzhalters (**Texte → Platzhalter für externe Inhalte → Erklärung**) stehen neben `{name}` drei Link-Platzhalter zur Verfügung; der übrige Text bleibt maskiert:

| Platzhalter | Link |
| --- | --- |
| `{privacy}` | eigene Datenschutzerklärung (Linktext: Text `privacy_policy`) |
| `{imprint}` | Impressum (Linktext: Text `imprint`) |
| `{service_privacy}` | Datenschutzerklärung des Dienstes, in neuem Tab (Linktext: Text `privacy_policy_of`) |

Beispiel: `Mit dem Laden werden Daten an {name} übertragen. Details in unserer {privacy}.` Ist die Seite oder Adresse nicht hinterlegt, erscheint der Linktext ohne Link.

Ist der Dienst nicht angelegt, inaktiv oder der aktuellen Domain nicht zugeordnet, lässt sich der Inhalt nicht laden: Der Platzhalter zeigt „Dieser Inhalt ist derzeit nicht verfügbar“ ohne Schaltflächen, denn ohne Dienst fehlen die Angaben im Hinweis und in der Datenschutzerklärung. Als Name dient die gleichnamige Vorlage, sofern es eine gibt. Angemeldete Redakteure sehen zusätzlich, welcher Dienst fehlt; in der Browser-Konsole steht eine Warnung.

Ohne PHP:

```html
<consent-embed service="youtube" label="Imagefilm" style="--ck-embed-ratio: 16/9">
    <template><iframe src="https://www.youtube-nocookie.com/embed/…" title="Imagefilm"></iframe></template>
</consent-embed>
```

Auch Script-Einbettungen funktionieren – Scripts im `<template>` werden nach dem Laden ausgeführt:

```html
<consent-embed service="instagram">
    <template>
        <blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/…"></blockquote>
        <script async src="https://www.instagram.com/embed.js"></script>
    </template>
</consent-embed>
```

### Inhalte aus dem Editor

CKEditor 5 und TinyMCE (Plugin `for_oembed`) speichern Einbettungen als

```html
<figure class="media"><oembed url="https://www.youtube.com/watch?v=…"></oembed></figure>
```

Diese Tags werden im Frontend automatisch in gesperrte Platzhalter umgewandelt (**Einstellungen → Einbettungen aus CKEditor 5 und TinyMCE sperren**, Standard: an). Der Renderer der Editoren ist dann nicht nötig.

| URL | Ergebnis |
| --- | --- |
| YouTube (`watch`, `shorts`, `live`, `embed`, `youtu.be`, auch mit Zeitmarke `t=`) | Player über `youtube-nocookie.com`, Dienst `youtube` |
| Vimeo (`vimeo.com/ID`, auch mit Hash für private Videos) | Player mit `dnt=1`, Dienst `vimeo` |
| Andere URL, deren Host bei einem Dienst hinterlegt ist | iframe mit der URL selbst (z. B. Google-Maps-Embed-Link) |
| Unbekannter Host | nur ein Link – nichts wird geladen |

Ist die Umwandlung abgeschaltet, geht es gezielt in der Modulausgabe: `echo \FriendsOfRedaxo\ConsentKit\Consent::oembed($html);`.

Fertige iframes (z. B. aus dem Quelltext-Modus des Editors) sind ein zweiter Fall: Mit **Einstellungen → iframes bekannter Dienste automatisch sperren** werden iframes ersetzt, deren Host bei einem Dienst unter *Erweitert → Hosts für eingebettete Inhalte* steht (Subdomains zählen mit). Bereits verpackte iframes bleiben unberührt. In eigenem Code geht dasselbe gezielt:

```php
use FriendsOfRedaxo\ConsentKit\Consent;
use FriendsOfRedaxo\ConsentKit\Embed;

$html = Embed::filter($html, Consent::config());
```

## Einstellungen erneut öffnen

Die schwebende Schaltfläche ist standardmäßig an. Alternativ oder zusätzlich – typischerweise im Footer:

```html
<a href="#consent-kit">Cookie-Einstellungen</a>
<button type="button" data-consent-kit-open>Cookie-Einstellungen</button>
```

Auch `.consent-kit-open` und das vom consent_manager bekannte `.consent_manager-show-box` funktionieren.

## Dienste-Übersicht für die Datenschutzerklärung

```html
REX_CONSENT_KIT[output=overview level=3]
```

oder in PHP `echo \FriendsOfRedaxo\ConsentKit\Consent::overview(3);`. Ausgegeben werden alle Dienste der aktuellen Domain in der aktuellen Sprache: Beschreibung, Anbieter, Datenschutz-Link, Tabelle der Cookies und Speichereinträge sowie ein Link zu den Cookie-Einstellungen. `level` ist die Überschriften-Ebene der Gruppen.
