# Dienste, Domains, Varianten

## Die Übersicht

Eine Tabelle, Gruppen als Zwischenzeilen:

- **Schalter links:** Dienst überall an oder aus. Inaktive Dienste erscheinen nicht im Hinweis und werden nie geladen.
- **Haken je Domain:** Ein Klick legt fest, ob der Dienst auf dieser Domain angeboten wird. Die Spalten erscheinen ab zwei Domains. Die letzte Domain lässt sich nicht abwählen – dafür gibt es den Schalter.
- **Pfeile:** Reihenfolge innerhalb der Gruppe bzw. der Gruppen. So erscheinen sie auch im Hinweis.
- **Marken:** „Unvollständig“ (es fehlt z. B. eine ID, der Dienst wird nicht ausgeliefert), „n Varianten“, Consent-Mode-Signale.

## Dienst aus Vorlage

**Dienst hinzufügen** öffnet die Vorlagen mit Suche und Gruppenfilter. Braucht die Vorlage Angaben (Mess-ID, Pixel-ID, Matomo-URL …), landet man direkt im Formular, der Cursor steht im ersten leeren Feld. Das Format wird schon im Browser geprüft.

Im Formular steht unter „Aus Vorlage“, wann die Angaben geprüft wurden, aus welchen Quellen sie stammen und – falls vorhanden – was sich nicht belegen ließ. **Auf Vorlage zurücksetzen** ersetzt alles außer Schlüssel, Gruppe, Status, Domains und den eingetragenen IDs.

## Eigener Dienst

Für alles ohne Vorlage. Die Reiter:

| Reiter | Inhalt |
| --- | --- |
| Allgemein | Name, Schlüssel, Gruppe, Beschreibung je Sprache, Anbieter, Datenschutz-Link, Domains |
| Cookies & Speicher | Was der Dienst im Browser ablegt: Art (Cookie, Local/Session Storage, IndexedDB), Name mit `*` als Platzhalter, Domain, Laufzeit, Zweck je Sprache |
| Scripts | Code, der erst nach Einwilligung läuft (siehe unten) |
| Ereignisse | Conversions ohne Programmierung: „Wenn Klick auf … / Seite … / Formular … dann melde Anfrage“ (siehe unten) |
| Varianten | Abweichungen je Domain oder Sprache |
| Erweitert | Consent-Mode-Signale; Domains, von denen der Dienst Inhalte einbettet (siehe unten) |

Der **Schlüssel** ist der Name, unter dem Templates und Module den Dienst ansprechen (`Consent::has('…')`, `data-consent="…"`). Nach dem Anlegen ist das Feld gesperrt; „Schlüssel ändern“ gibt es nach einer Rückfrage frei – Templates müssen dann angepasst werden, und Besucher werden zu diesem Dienst erneut gefragt.

### Die fünf Code-Felder

| Feld | Wann es läuft | Inhalt |
| --- | --- | --- |
| HTML im `<head>` | einmal nach Einwilligung | vollständiges HTML inklusive `<script>`-Tags |
| HTML am Ende des `<body>` | einmal nach Einwilligung | wie oben |
| JavaScript bei Einwilligung | auf jeder Seite, solange die Einwilligung besteht | reines JavaScript, z. B. `fbq('consent','grant')` |
| JavaScript bei Widerruf | beim Abwählen | reines JavaScript, z. B. der Revoke-Aufruf des Anbieters |
| JavaScript vor jeder Entscheidung | immer, als Erstes im `<head>` | z. B. „consent default denied“. Darf nichts laden und keine Cookies setzen. |

Externe Scripts behalten ihre Reihenfolge, solange sie kein `async` tragen. Ein vorhandener CSP-Nonce wird übernommen.

Beim Widerruf werden außerdem alle unter „Cookies & Speicher“ eingetragenen Cookies und Storage-Einträge gelöscht, soweit der Browser das zulässt (eigene Domain und deren Überdomains; Cookies fremder Domains sind für die Website nicht erreichbar).

### Domains eingebetteter Inhalte

Manche Dienste laufen nicht als Script, sondern als eingebetteter Inhalt: ein YouTube-Video, eine Google-Karte, ein Facebook-Post. Damit Consent Kit solche iframes und Editor-Einbettungen erkennt und diesem Dienst zuordnet, stehen unter **Erweitert** die Domains, von denen der Dienst einbettet – bei YouTube etwa `youtube.com` und `youtube-nocookie.com`, bei Facebook `facebook.com`. Subdomains zählen automatisch mit.

Genutzt wird die Liste an drei Stellen: von der automatischen Umwandlung der `<oembed>`-Tags aus den Editoren, von „iframes bekannter Dienste automatisch sperren“ und bei `Consent::embed()` nur zur Anzeige des Namens. Die Vorlagen bringen die passenden Domains mit. Dienste, die nichts einbetten (Statistik, Pixel), lassen das Feld leer.

## Ereignisse (Conversions)

Im Reiter **Ereignisse** legt man ohne Code fest, wann ein Dienst eine Conversion melden soll: „Wenn Klick auf `a[href^="mailto:"]` dann melde Anfrage“. Auslöser sind Klick, Seitenaufruf und Formular; die Vorlage kennt den Aufruf je Anbieter. Ausführlich mit Beispielen: [Conversions und Ereignisse](10_ereignisse.md).

## Varianten je Domain oder Sprache

Gleicher Dienst, andere Angaben – typischerweise eine andere Website-ID je Domain oder ein eigenes Konto für die englische Seite.

1. Dienst öffnen, Reiter **Varianten**, **Variante hinzufügen**.
2. „Gilt für Domain“ und/oder „Gilt für Sprache“ wählen.
3. Nur eintragen, was abweicht. Leere Felder übernehmen den Wert des Dienstes. Unter „Eigener Code“ lässt sich auch jedes der fünf Code-Felder ersetzen.

Passen mehrere Varianten, gewinnt die genaueste:

1. Domain **und** Sprache
2. nur Domain
3. nur Sprache
4. alle Domains/alle Sprachen
5. die Angaben des Dienstes selbst

In jedem Code stehen außerdem `{{lang}}` (z. B. `de`) und `{{domain}}` (z. B. `example.org`) bereit – oft reicht das schon:

```html
<script src="https://cdn.example.com/widget.js" data-locale="{{lang}}"></script>
```

Varianten ändern, *wie* ein Dienst geladen wird, nicht *ob* er angeboten wird. Dafür ist die Domain-Matrix da.

## Gruppen

Gruppen bündeln Dienste im Hinweis; Besucher können eine Gruppe als Ganzes schalten oder aufklappen und jeden Dienst einzeln wählen. Eine Gruppe mit **Notwendig** ist immer aktiv und nicht abwählbar – nur für technisch zwingend erforderliche Dienste verwenden. Gruppen ohne aktive Dienste erscheinen nicht.

## Wann Besucher erneut gefragt werden

Jeder Dienst hat einen Fingerabdruck aus Gruppe, Schlüssel, Anbieter und den Einträgen unter „Cookies & Speicher“. Ändert sich einer davon oder kommt ein Dienst hinzu, wird **nur dieser Dienst** erneut abgefragt; alle anderen Entscheidungen bleiben gültig. Texte, Scripts, IDs und Reihenfolge lösen nichts aus. Wer alle Entscheidungen verwerfen will: **Einstellungen → Alle Besucher erneut fragen**.

## Eigene Vorlagen

JSON-Dateien im Data-Ordner des AddOns (`…/data/addons/consent_kit/presets/*.json`) werden zusätzlich geladen, überstehen Updates und überschreiben mitgelieferte Vorlagen mit gleichem Schlüssel. Das Format beschreibt die Datei `presets/FORMAT.md` im AddOn-Ordner; ein Beispiel steht in der [API-Referenz](07_api.md#vorlagen-format).
