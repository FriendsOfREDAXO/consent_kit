# Eigene Vorlagen

Eine Vorlage ist ein fertiger Dienst als JSON: Name, Anbieter, Scripts, Cookies – und die Felder, die beim Anlegen noch auszufüllen sind. Unter **Dienste → Dienst hinzufügen** steht sie zur Wahl, ein Klick legt den Dienst an.

Mitgelieferte Vorlagen liegen im AddOn-Ordner (`presets/*.json`) und werden bei Updates ersetzt. Eigene Vorlagen gehören in den Data-Ordner:

```
…/data/addons/consent_kit/presets/*.json
```

Beide Orte werden geladen. Eine eigene Vorlage mit demselben `key` überschreibt die mitgelieferte – so lässt sich eine Vorlage anpassen, ohne dass das nächste Update die Änderung wegräumt.

## Das Wichtigste: Platzhalter

Eine Vorlage enthält keine Zugangsdaten, sondern Lücken. Im Code steht `{{measurement_id}}`, im Formular ein Feld „Mess-ID“, und der Admin trägt dort `G-ABC123` ein. Erst beim Ausliefern setzt Consent Kit den Wert ein.

Das ist der Grund, warum eine Vorlage übertragbar ist: Der Google-Analytics-Code ist auf jeder Website derselbe, nur die ID nicht.

### Die drei Teile

**1. Die Definition unter `params`** – daraus baut das Formular das Eingabefeld:

```json
"params": [
  {
    "key": "measurement_id",
    "label": { "de": "Mess-ID", "en": "Measurement ID" },
    "placeholder": "G-XXXXXXXXXX",
    "pattern": "^G-[A-Z0-9]+$"
  }
]
```

| Feld | Wirkung |
| --- | --- |
| `key` | Der Name des Platzhalters. `{{measurement_id}}` im Code. Nur `a–z`, `0–9`, `_`. |
| `label` | Die Beschriftung des Feldes, je Sprache. |
| `placeholder` | Der graue Beispieltext im leeren Feld. Zeigt das erwartete Format. |
| `pattern` | Optional. Regulärer Ausdruck, gegen den der Browser die Eingabe prüft (`pattern`-Attribut). Ein Tippfehler fällt damit schon beim Eintippen auf. |

**2. Der Platzhalter im Code** – in jedem der fünf Code-Felder und in den Ereignis-Aufrufen:

```json
"html_head": "<script async src=\"https://www.googletagmanager.com/gtag/js?id={{measurement_id}}\"></script>"
```

**3. Der eingetragene Wert** – steht beim Dienst in dieser Installation, nicht in der Vorlage.

### Was passiert, wenn ein Feld leer bleibt

Bleibt ein Platzhalter unaufgelöst, wird der Dienst **nicht ausgeliefert**. In der Übersicht trägt er die Marke „Unvollständig“. Lieber gar kein Script als eines mit `{{measurement_id}}` in der URL.

### Immer verfügbar: `{{lang}}` und `{{domain}}`

Zwei Platzhalter setzt das AddOn selbst, sie brauchen keine `params`-Definition:

| Platzhalter | Wert |
| --- | --- |
| `{{lang}}` | Zweibuchstabiger Sprachcode der aktuellen Seite, z. B. `de` |
| `{{domain}}` | Host der aktuellen Domain, z. B. `example.org` |

```json
"html_body": "<script src=\"https://cdn.example.com/w.js\" data-locale=\"{{lang}}\"></script>"
```

In den Ereignis-Aufrufen kommt außerdem `{{label}}` dazu – die Kennung, die je Ereignis eingetragen wird (bei Google Ads etwa das Conversion-Label).

### Beispiele aus den mitgelieferten Vorlagen

| Dienst | Feld | Eingabe sieht so aus | `pattern` |
| --- | --- | --- | --- |
| Google Analytics 4 | `measurement_id` | `G-XXXXXXXXXX` | `^G-[A-Z0-9]+$` |
| Google Tag Manager | `container_id` | `GTM-XXXXXXX` | `^GTM-[A-Z0-9]+$` |
| Google Ads | `conversion_id` | `AW-XXXXXXXXXX` | `^AW-[0-9]+$` |
| Matomo | `matomo_url`, `site_id` | `https://matomo.example.com/`, `1` | `^https?://.+/$`, `^[0-9]+$` |
| Meta Pixel | `pixel_id` | `123456789012345` | `^[0-9]+$` |

Matomo zeigt den Normalfall bei selbst gehosteten Diensten: zwei Felder, weil neben der ID auch die Adresse der Installation variabel ist.

## Exportieren

**Werkzeuge → Eigene Vorlagen → Exportieren**: alle Dienste oder eine Auswahl, als JSON-Datei zum Herunterladen. Einzeln geht es auch direkt im Dienst – im Formular unten rechts **Diesen Dienst als Vorlage exportieren**.

Was mitkommt: Name, Anbieter, Datenschutz-Link, Beschreibungen in allen Sprachen, die fünf Code-Felder, Cookies und Storage-Einträge mit Laufzeiten und Zwecken, Consent-Mode-Signale, Embed-Hosts, die `params`-Definitionen.

Was bewusst draußen bleibt, weil es zu *dieser* Installation gehört:

- **die eingetragenen Werte** (Mess-IDs, Pixel-IDs, Matomo-URL) – die Platzhalter bleiben stehen
- Domains und die Domain-Matrix
- Varianten
- Status und Reihenfolge
- die konfigurierten Ereignis-Auslöser (die Aufruf-*Vorlagen* je Ereignis kommen mit)

Ein Export ist damit gefahrlos weiterzugeben: Er enthält keine Konten und keine Kennungen.

Hat der Dienst keine Ursprungsvorlage, liest der Export die Platzhalter aus dem Code und legt für jeden eine `params`-Definition mit dem Schlüssel als Beschriftung an. Label, `placeholder` und `pattern` sind dann von Hand nachzutragen – dafür ist die Datei ja da.

`verified` wird auf das Exportdatum gesetzt, `sources` aus der Ursprungsvorlage übernommen. Beides bitte prüfen, bevor die Datei weitergereicht wird.

## Importieren

**Werkzeuge → Eigene Vorlagen → Importieren**: Datei wählen, importieren. Geprüft wird vor dem Speichern:

- `key`, `name` und `group` sind vorhanden
- der Schlüssel besteht nur aus `a–z`, `0–9`, `_`
- die Gruppe existiert in dieser Installation
- jeder Eintrag unter `items` hat einen Namen, eine bekannte Art und eine bekannte Laufzeit-Einheit
- die Consent-Mode-Signale sind gültig

Fehlerhafte Einträge werden einzeln übersprungen und benannt; der Rest der Datei wird übernommen. Gibt es schon eine Datei gleichen Namens, landet die neue als `name-2.json` daneben – es sei denn, **Gleichnamige Datei überschreiben** ist angehakt.

Der Import legt **keine Dienste an**. Er stellt Vorlagen bereit; angelegt wird danach wie immer über **Dienste → Dienst hinzufügen**.

Darunter listet die Seite die vorhandenen eigenen Vorlagen mit ihren Schlüsseln, zum Herunterladen und Löschen. Eine Vorlage zu löschen entfernt keine bereits angelegten Dienste.

## Eine Vorlage von Hand schreiben

Der kürzeste brauchbare Fall – ein Dienst mit einem auszufüllenden Feld:

```json
{
  "services": [
    {
      "key": "my_chat",
      "name": "Mein Chat",
      "group": "functional",
      "provider": "Beispiel GmbH, Musterweg 1, 12345 Musterstadt",
      "privacy_url": "https://example.com/datenschutz",
      "description": {
        "de": "Chat-Fenster für Support-Anfragen.",
        "en": "Chat window for support requests."
      },
      "params": [
        {
          "key": "widget_id",
          "label": { "de": "Widget-ID", "en": "Widget ID" },
          "placeholder": "abc123",
          "pattern": "^[a-z0-9]+$"
        }
      ],
      "html_body": "<script src=\"https://chat.example.com/w/{{widget_id}}.js\" data-lang=\"{{lang}}\"></script>",
      "items": [
        {
          "type": "cookie",
          "name": "chat_session",
          "host": "",
          "duration": { "value": 30, "unit": "days" },
          "purpose": {
            "de": "Erkennt die Chat-Sitzung wieder.",
            "en": "Recognises the chat session."
          }
        }
      ],
      "sources": ["https://example.com/docs/cookies"],
      "verified": "2026-09-23"
    }
  ]
}
```

Eine Datei kann beliebig viele Dienste enthalten. Alle Felder beschreibt die Datei `presets/FORMAT.md` im AddOn-Ordner.

Der bequemere Weg: den Dienst im Backend anlegen, ausprobieren, dann exportieren und die Datei nachbearbeiten.

## Beim Weitergeben prüfen

Vorlagen sind eine Behauptung darüber, was ein Dienst tut. Wer eine weitergibt, sollte sie belegen können:

- **`items` vollständig?** Jedes Cookie und jeder Storage-Eintrag mit Name, Laufzeit und Zweck. Der Cookie-Scanner unter **Werkzeuge** zeigt, was tatsächlich landet.
- **`sources` gesetzt?** Die Cookie-Dokumentation des Anbieters, nicht ein Blogbeitrag.
- **`verified` aktuell?** Anbieter ändern Cookies ohne Ankündigung.
- **`note` gesetzt,** wenn etwas nicht zu belegen war – der Text erscheint dem Admin im Dienst-Formular.
- **`gcm_signals` nur bei Google-Tags.**
- **Keine IDs im Code.** Statt der eigenen Kennung gehört dort ein Platzhalter hin.
