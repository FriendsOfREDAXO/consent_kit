# Conversions und Ereignisse

Ein Tracking-Pixel (OpenAI, Meta, Google Ads …) misst von allein nichts. Es wartet darauf, dass die Website ihm sagt: „Jetzt ist etwas passiert – eine Anfrage, eine Buchung, ein Besuch der Dankeseite.“ Genau das legt der Reiter **Ereignisse** im Dienst fest, ohne JavaScript und ohne Kenntnis der Anbieter-Schnittstellen.

## Wie es funktioniert

Jede Zeile ist ein Satz nach dem Muster **„Wenn … dann melde …“**:

| Feld | Bedeutung |
| --- | --- |
| **Wenn …** | Der Auslöser: *Klick auf*, *Aufruf der Seite* oder *Formular gesendet*. |
| **Ziel** | Worauf sich der Auslöser bezieht: ein CSS-Selektor (Klick, Formular) oder ein Pfad (Seite). Das Feld zeigt je Auslöser ein Beispiel. |
| **… dann melde** | Das Ereignis: *Anfrage (Lead)*, *Registrierung*, *Terminbuchung*, *Wichtige Seite aufgerufen* oder *Eigener Code*. |
| **Kennung beim Anbieter** | Nur bei Anbietern, die je Ereignis eine eigene Kennung brauchen (Google Ads: das Conversion-Label). |

Aus den Zeilen erzeugt Consent Kit ein kleines Script mit dem passenden Aufruf des Anbieters. Es wird

- **nur nach Einwilligung** in diesen Dienst aktiviert – ohne Zustimmung passiert nichts, auch kein Fehler in der Konsole,
- **einmal pro Seite** eingerichtet, auch bei später per AJAX eingefügten Inhalten (Klicks werden über das Dokument abgefangen),
- **bei Widerruf** nicht mehr geladen; die Seite lädt dann neu.

## Schritt für Schritt: Anfragen per WhatsApp und E-Mail zählen

1. **Dienste → Dienst hinzufügen**, Vorlage wählen (z. B. „OpenAI Measurement Pixel“), die Pixel-ID eintragen.
2. Reiter **Ereignisse → Ereignis hinzufügen**.
3. *Wenn* „Klick auf“, *Ziel* `a[href^="https://wa.me/"], a[href^="mailto:"]`, *dann melde* „Anfrage (Lead)“.
4. **Speichern**.

Sobald ein Besucher dem Dienst zugestimmt hat und auf einen WhatsApp- oder E-Mail-Link klickt, meldet die Seite `lead_created` an das Pixel.

## Die drei Auslöser

### Klick auf

Ein CSS-Selektor; getroffen wird auch ein Klick auf ein Kind-Element (Icon im Button). Beispiele:

| Ziel | Trifft |
| --- | --- |
| `a[href^="mailto:"]` | alle E-Mail-Links |
| `a[href^="tel:"]` | alle Telefon-Links |
| `a[href^="https://wa.me/"], a[href^="https://api.whatsapp.com/"]` | WhatsApp-Links |
| `.btn-angebot` | Schaltflächen mit dieser Klasse |
| `a[href$=".pdf"]` | PDF-Downloads |
| `#newsletter button[type="submit"]` | die Absende-Schaltfläche eines bestimmten Formulars |

Mehrere Selektoren werden mit Komma getrennt.

### Aufruf der Seite

Ein Pfad. Beginnt er mit `/`, muss der Pfad der aktuellen Seite damit **beginnen** (`/danke/` trifft `/danke/` und `/danke/?id=5`). Ohne führenden `/` reicht es, wenn er **enthalten** ist (`danke` trifft auch `/kontakt/danke/`). Typischer Einsatz: die Dankeseite nach einem Formular, weil sie nur erreicht wird, wenn das Formular abgeschickt wurde.

### Formular gesendet

Ein Selektor für das `<form>`-Element, z. B. `#kontakt` oder `form.yform`. Gemeldet wird beim Absenden – auch dann, wenn die Prüfung auf dem Server das Formular anschließend ablehnt. Wer nur erfolgreiche Anfragen zählen will, nimmt stattdessen die Dankeseite.

## Was die Vorlagen melden

| Vorlage | Anfrage | Registrierung | Terminbuchung | Wichtige Seite |
| --- | --- | --- | --- | --- |
| OpenAI Measurement Pixel | `lead_created` | `registration_completed` | `appointment_scheduled` | `page_viewed` |
| Meta Pixel | `Lead` | `CompleteRegistration` | `Schedule` | `ViewContent` |
| Google Ads (auch über Tag Manager) | `conversion` mit `send_to: AW-…/Label` | dito | dito | dito |
| Google Analytics 4 (auch über Tag Manager) | `generate_lead` | `sign_up` | `generate_lead` (Label `appointment`) | `page_view` |
| Matomo | `trackEvent('Conversion', 'Lead', Kennung)` | `… 'Registration'` | `… 'Appointment'` | `… 'PageView'` |

Die Aufrufe entsprechen der jeweiligen Anbieterdokumentation (Quellen beim Dienst). Alle anderen Dienste bieten nur „Eigener Code“.

### Google Ads: das Conversion-Label

Google Ads unterscheidet Conversions nicht nach Ereignisnamen, sondern nach einem **Label** pro Conversion-Aktion. Es steht im Ads-Konto unter *Zielvorhaben → Conversions → Aktion → Tag einrichten* im Ereignis-Snippet: `send_to: 'AW-123456789/AbC-D_efG-h12_34-567'` – der Teil nach dem Schrägstrich ist das Label. Es gehört in das Feld **Kennung beim Anbieter**; die Conversion-ID (`AW-…`) kommt aus den Angaben des Dienstes.

## Eigener Code

Für Ereignisse, die es nicht in der Liste gibt (z. B. `Purchase`, `AddToCart`, eigene Namen): *… dann melde* „Eigener Code“ wählen und den Aufruf des Anbieters eintragen. Die Angaben des Dienstes stehen als Platzhalter bereit:

```js
gtag('event', 'conversion', { send_to: '{{conversion_id}}/AbC-D_efG', value: 25.0, currency: 'EUR' });
```

```js
oaiq('measure', 'custom', { type: 'custom' }, { custom_event_name: 'brochure_download' });
```

Der Code läuft, wenn der Auslöser eintritt; das Anbieter-Script ist zu diesem Zeitpunkt eingebunden.

## Was hier nicht hingehört

**Käufe mit Betrag.** Betrag, Währung und Bestellnummer sind je Bestellung anders und stehen nur im Shop-Template. Dafür den Aufruf dort als gesperrtes Script hinterlegen:

```html
<script type="text/plain" data-consent="google_ads">
    gtag('event', 'conversion', { send_to: 'AW-123456789/AbC-D_efG', value: <?= $order->total ?>, currency: 'EUR', transaction_id: '<?= $order->id ?>' });
</script>
```

`data-consent` sorgt dafür, dass das Script nur nach Einwilligung läuft. Alles Weitere dazu in [Einbindung im Template](03_einbindung.md#eigene-scripts-sperren).

## Prüfen, ob es funktioniert

1. Im Frontend dem Dienst zustimmen (vorher ggf. `ConsentKit.reset()` in der Konsole).
2. Entwicklerwerkzeuge → **Netzwerk** öffnen, Auslöser ausführen (Link klicken, Dankeseite aufrufen). Es erscheint eine Anfrage an den Anbieter – OpenAI: `bzrcdn.openai.com` bzw. Ereignis-Aufrufe an `openai.com`, Meta: `facebook.com/tr`, Google: `googleads.g.doubleclick.net`.
3. Die Anbieter haben Prüfwerkzeuge: OpenAI liefert mit `debug: true` Konsolenausgaben (nur zum Testen, nicht in der Vorlage), Meta den „Meta Pixel Helper“ (Browser-Erweiterung), Google Ads den „Tag Assistant“.
4. Ohne Zustimmung darf **keine** dieser Anfragen erscheinen.

Fehler im eigenen Code stehen in der Konsole mit dem Präfix `[consent-kit] event`.

## Für Entwickler

- Gespeichert werden die Zeilen als JSON in `rex_consent_kit_service.events` (`trigger`, `target`, `event`, `label`, `code`).
- Vorlagen liefern die Aufrufe im Feld `events` (`{"lead": "…", "registration": "…", "appointment": "…", "page_view": "…"}`), mit `{{label}}` für die Kennung und `{{param}}` für Angaben des Dienstes – siehe `presets/FORMAT.md`. Eigene Vorlagen im Data-Ordner können das ebenso.
- `KLXM\ConsentKit\Events::build($rows, $templates)` erzeugt das Script; es landet als `jsEvents` in der Frontend-Konfiguration und wird von der Komponente einmal pro Seite nach `html_head`/`html_body` ausgeführt, vor `js_accept`.
