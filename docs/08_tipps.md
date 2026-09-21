# Tipps und Tricks

## Testen

- **Von vorn beginnen:** in der Browser-Konsole `ConsentKit.reset()`. Alternativ ein privates Fenster.
- **Was ist gerade erlaubt?** `ConsentKit.accepted()` – und `ConsentKit.has('matomo')` für einen einzelnen Dienst.
- **Wurde wirklich nichts geladen?** Entwicklerwerkzeuge → Netzwerk, Seite ohne Entscheidung laden: Es dürfen nur Anfragen an die eigene Domain erscheinen. Danach **Werkzeuge → Cookie-Scanner**: Er zeigt Cookies und Storage-Einträge, die bei keinem Dienst dokumentiert sind.
- **GPC ausprobieren:** Firefox → Einstellungen → Datenschutz → „Websites anweisen, meine Daten nicht zu verkaufen oder weiterzugeben“. In Chrome per Konsole vor dem Laden nicht simulierbar – Firefox oder Brave nehmen.
- **Erneute Abfrage testen:** Bei einem Dienst einen Cookie-Eintrag ergänzen und speichern. Beim nächsten Seitenaufruf erscheint der Hinweis erneut; unter „Einstellungen“ sind die bisherigen Entscheidungen vorausgewählt, bereits erlaubte Dienste laufen bis zur neuen Entscheidung weiter.

## Ein Dienst wird nicht geladen

1. Steht in der Übersicht **„Unvollständig“**? Dann fehlt eine Angabe – auch in einer Variante, wenn der Dienst nur dort eine ID bekommt.
2. Ist der Haken für die **Domain** gesetzt? Der Host wird ohne `www.` und Port verglichen.
3. Bleibt in einem Code-Feld ein `{{platzhalter}}` offen, wird der Dienst für diese Domain/Sprache nicht ausgeliefert.
4. Fehler in eigenen Scripts erscheinen in der Konsole mit dem Präfix `[consent-kit]` und dem Schlüssel des Dienstes.
5. Bei strenger **Content Security Policy** müssen die Hosts der Anbieter in `script-src`/`connect-src`/`frame-src` erlaubt sein. Den Nonce übernimmt die Komponente vom ersten Script mit `nonce`-Attribut; das eigene Inline-Script nutzt den REDAXO-Nonce (`rex_response::getNonce()`).

## Eine Karte erst nach Einwilligung initialisieren

```html
<div id="map" hidden></div>
<script type="text/plain" data-consent="google_maps" data-src="https://maps.googleapis.com/maps/api/js?key=…&callback=initMap"></script>
<script>
    function initMap() {
        const el = document.getElementById('map');
        el.hidden = false;
        new google.maps.Map(el, { center: { lat: 51.5, lng: 7.4 }, zoom: 12 });
    }
</script>
```

Soll stattdessen ein Platzhalter mit Schaltflächen erscheinen, das Ganze in `<consent-embed service="google_maps"><template>…</template></consent-embed>` legen.

## Unterschiedliche Matomo-Seiten je Domain

Ein Dienst „Matomo“, im Reiter **Varianten** je Domain eine Variante mit abweichender Website-ID. Besucher sehen überall denselben Dienst, das Protokoll bleibt vergleichbar. Für eine andere Matomo-Instanz je Domain zusätzlich die URL in der Variante überschreiben.

## Ein Dienst nur auf einer Sprachversion

Dafür gibt es keinen Schalter – Sprachen sind keine Domains. Zwei Wege:

- Liegen die Sprachen auf eigenen Domains (YRewrite), den Haken in der Matrix nur dort setzen.
- Sonst den Dienst überall anbieten und das Script per Variante für die übrigen Sprachen „leeren“: Variante mit der Sprache anlegen und unter „Eigener Code“ im betreffenden Code-Feld einen Kommentar eintragen (`<!-- nicht auf dieser Sprache -->`). Ein nicht leeres Feld ersetzt den Code des Dienstes.

## Matomo ohne Cookies

Wer Matomo cookielos und ohne Einwilligung betreiben will, bindet es ganz normal im Template ein und legt den Dienst – nach eigener rechtlicher Prüfung – in die Gruppe **Notwendig**, ohne Script, nur zur Information. Er erscheint dann im Hinweis als „Immer aktiv“ und in der Dienste-Übersicht der Datenschutzerklärung.

## Hinweis auf bestimmten Seiten ruhig halten

Mit × lässt sich der Hinweis überall schließen. Wer das × abschaltet, bekommt auf Datenschutzerklärung und Impressum automatisch eine nicht blockierende Box. Für weitere Seiten das Element selbst ins Template dieser Seite setzen – seine Attribute haben Vorrang vor den Einstellungen:

```html
<consent-kit layout="box" position="bottom-right"></consent-kit>
```

## Eigene Schaltfläche statt schwebendem Button

Schwebende Schaltfläche abschalten und im Footer verlinken:

```html
<a href="#consent-kit">Cookie-Einstellungen</a>
```

Der Link muss auf **jeder** Seite erreichbar sein. Fehlt beides, können Besucher ihre Einwilligung nicht widerrufen.

## Komponente an das Design anpassen, ohne den Editor

Variablen in das eigene Stylesheet, am besten mit den vorhandenen Design-Tokens:

```css
consent-kit, consent-embed {
    --ck-font: var(--font-body);
    --ck-accent: var(--color-primary);
    --ck-button-bg: var(--color-primary);
    --ck-button-border: var(--color-primary);
    --ck-button-text: var(--color-on-primary);
    --ck-radius: var(--radius-m);
}
```

Danach im Design-Editor „Standard wiederherstellen“, sonst gewinnen die dort gespeicherten Werte (sie werden als Inline-Style gesetzt).

## Auf Entscheidungen reagieren

```php
// z. B. Kennzahl an ein eigenes Dashboard melden
rex_extension::register('CONSENT_KIT_SAVED', static function (rex_extension_point $ep) {
    rex_logger::factory()->info('Consent {id}: {action}', ['id' => $ep->getSubject(), 'action' => $ep->getParam('action')]);
});
```

```js
ConsentKit.onChange(({ accepted }) => {
    document.documentElement.classList.toggle('has-video-consent', accepted.includes('youtube'));
});
```

## Caching

- Die Konfiguration liegt je Domain und Sprache als JSON im REDAXO-Cache und wird bei jedem Speichern im AddOn verworfen. „Cache löschen“ im System baut sie neu auf.
- Die Seite selbst darf gecacht werden: Im HTML steht nichts Besucherspezifisches, die Entscheidung liest die Komponente im Browser aus dem Cookie. Nur `Consent::has()` in PHP verträgt keinen Full-Page-Cache.
- Nach Änderungen an JS/CSS des AddOns in Entwicklungsumgebungen mit getrenntem Asset-Ordner: `php redaxo/bin/console assets:sync`.

## Mehrere Websites, eine Installation

Jede YRewrite-Domain ist eine Spalte in der Matrix und kann eigene Rechtstexte haben. Der Cookie gilt pro Host, Besucher entscheiden also je Website getrennt. Das Protokoll führt die Domain mit und lässt sich danach filtern bzw. exportieren.

## Häufige Fragen

**Was passiert beim Schließen über das ×?** Es zählt als „keine Entscheidung“, nie als Einwilligung: Optionale Dienste bleiben aus, nichts wird protokolliert. Damit der Hinweis nicht auf jeder Seite wiederkommt, merkt sich der Browser das Schließen im Session Storage (`consent_kit_dismissed`) – bis das Fenster geschlossen wird oder sich die Dienste ändern. Der Eintrag ist beim notwendigen Dienst „Consent Kit“ dokumentiert.

**Warum ist der Dialog nicht mittig?** Nur in einem Fall: Die Schließen-Schaltfläche (×) ist abgeschaltet und die Seite ist als Datenschutzerklärung oder Impressum eingetragen. Dann erscheint statt des erzwungenen Dialogs eine Box, damit der Text lesbar bleibt. Ist dort versehentlich die Startseite gewählt, warnt die Dienste-Seite im Status.

**Warum erscheint kein Hinweis?** Es ist kein einwilligungspflichtiger Dienst aktiv und vollständig, der Browser sendet GPC (dann wurde automatisch abgelehnt – die schwebende Schaltfläche ist trotzdem da), oder es liegt bereits eine Entscheidung vor.

**Kann ich „Akzeptieren“ farblich hervorheben?** Nein, absichtlich nicht. Gleichwertige Schaltflächen sind eine Anforderung der Aufsichtsbehörden und der Rechtsprechung.

**Werden bei einem Widerruf alle Cookies gelöscht?** Alle dokumentierten, die der Browser der Website zugänglich macht. Cookies fremder Domains (z. B. `.youtube.com`) kann keine Website löschen; sie werden nur nicht mehr gesetzt, weil der Inhalt nicht mehr geladen wird.

**Muss ich das Protokoll aufbewahren?** Es gibt keine gesetzliche Frist. Der Nachweis sollte so lange möglich sein, wie man sich auf die Einwilligung beruft; der Standard von drei Jahren orientiert sich an der regelmäßigen Verjährung.

**Gilt eine Ablehnung genauso lange wie eine Zustimmung?** Ja. Wer ablehnt, wird erst nach Ablauf der Gültigkeit oder bei neuen Diensten wieder gefragt.
