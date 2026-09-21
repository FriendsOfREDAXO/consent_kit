# API-Referenz

## PHP

Namespace `KLXM\ConsentKit`.

### `Consent`

| Methode | Rückgabe | Zweck |
| --- | --- | --- |
| `Consent::has(string $key)` | `bool` | `true`, wenn der Dienst notwendig ist oder ihm in seiner aktuellen Fassung zugestimmt wurde. Liest den Cookie des aktuellen Requests. |
| `Consent::embed(string $key, string $html, array $options = [])` | `string` | Verpackt Markup in `<consent-embed>`. Optionen: `title` (Beschriftung), `ratio` (z. B. `'16/9'`). |
| `Consent::overview(int $headingLevel = 3)` | `string` | Dienste-Übersicht als HTML für die Datenschutzerklärung. |
| `Consent::state()` | `?array` | Inhalt des Cookies: `id`, `e`, `rev`, `ts`, `a`, `r` – oder `null`. |
| `Consent::config()` | `array` | Fertige Frontend-Konfiguration für aktuelle Domain und Sprache (aus dem Cache). |
| `Consent::domain()` | `array` | Domain-Eintrag zum aktuellen Host: `id`, `host`, `privacy_article_id`, `imprint_article_id`. |

```php
use KLXM\ConsentKit\Consent;

foreach (Consent::config()['groups'] as $group) {
    foreach ($group['services'] as $service) {
        echo $service['name'], ': ', Consent::has($service['key']) ? 'ja' : 'nein', "\n";
    }
}
```

### Weitere Klassen

| Aufruf | Zweck |
| --- | --- |
| `Frontend::head()` | Ausgabe für den `<head>` (entspricht `REX_CONSENT_KIT[]`) |
| `Embed::filter(string $html, array $config)` | iframes bekannter Hosts im HTML durch Platzhalter ersetzen |
| `Cache::clear()` | Konfigurations-Cache leeren (passiert bei jedem Speichern im Backend und beim REDAXO-Cache-Reset von selbst) |
| `Repository::services(bool $onlyActive = false)`, `::groups()`, `::domains()` | Rohdaten lesen |
| `Repository::saveService(int $id, array $data, array $items, ?array $variants = null)` | Dienst anlegen (`$id = 0`) oder ändern |
| `PresetRepository::all()`, `::get($key)`, `::toService($key)` | Vorlagen lesen bzw. in die Form von `saveService()` bringen |
| `Log::purge(?int $days = null)`, `Log::stats(int $days = 30)` | Protokoll bereinigen, Kennzahlen |

Dienst per Code aus einer Vorlage anlegen, z. B. in der `install.php` eines Projekt-AddOns:

```php
use KLXM\ConsentKit\PresetRepository;
use KLXM\ConsentKit\Repository;

if (!Repository::serviceKeyExists('matomo')) {
    [$data, $items] = PresetRepository::toService('matomo');
    $data['params'] = ['matomo_url' => 'https://stats.example.org/', 'site_id' => '3'];
    Repository::saveService(0, $data, $items, [
        ['domain_id' => 2, 'clang' => '', 'params' => ['site_id' => '7']],
    ]);
}
```

### REX_VAR

| Ausgabe | Zweck |
| --- | --- |
| `REX_CONSENT_KIT[]` | Einbindung im `<head>` |
| `REX_CONSENT_KIT[output=overview level=3]` | Dienste-Übersicht |

### Extension Points

| Extension Point | Subject | Parameter | Zweck |
| --- | --- | --- | --- |
| `CONSENT_KIT_CONFIG` | Konfiguration (`array`) | `domain`, `clang` | Konfiguration vor dem Cachen anpassen. Rückgabe ersetzt das Subject. |
| `CONSENT_KIT_SAVED` | Einwilligungs-ID | `action`, `accepted`, `rejected`, `revision`, `gpc`, `host` | Läuft nach jedem Protokolleintrag. |

```php
rex_extension::register('CONSENT_KIT_CONFIG', static function (rex_extension_point $ep) {
    $config = $ep->getSubject();
    if ('shop.example.org' === $ep->getParam('domain')['host']) {
        $config['layout'] = 'bar';
    }
    return $config;
});
```

`CONSENT_KIT_CONFIG` läuft nur beim Aufbau des Caches, nicht bei jedem Seitenaufruf. Wer dort Dienste entfernt oder hinzufügt, verändert nicht den protokollierten Stand – dafür sind Dienste und Domain-Matrix der richtige Ort.

### Konsole und Cronjob

```
php redaxo/bin/console consent_kit:log-purge [--days=90]
```

Cronjob-Typ: „Consent Kit: Protokoll bereinigen“.

## JavaScript

### `window.ConsentKit`

| Aufruf | Rückgabe | Zweck |
| --- | --- | --- |
| `ConsentKit.has('matomo')` | `boolean` | Einwilligung für den Dienst (notwendige Dienste: immer `true`) |
| `ConsentKit.accepted()` | `string[]` | Schlüssel aller akzeptierten optionalen Dienste |
| `ConsentKit.open()` | – | Einstellungen öffnen |
| `ConsentKit.onChange(fn)` | – | `fn({ accepted, rejected, action })` bei jeder Entscheidung |
| `ConsentKit.reset()` | – | Cookie löschen und neu laden – praktisch beim Testen |

Die Komponente ist ein ES-Modul und startet nach dem Parsen des Dokuments. Code, der früher läuft, wartet auf `consentkit:ready`:

```js
document.addEventListener('consentkit:ready', () => {
    if (ConsentKit.has('maps')) initMap();
});
document.addEventListener('consentkit:change', (event) => {
    if (event.detail.accepted.includes('maps')) initMap();
});
```

### Ereignisse

| Ereignis (auf `document`) | `event.detail` |
| --- | --- |
| `consentkit:ready` | `{ accepted: [], rejected: [], action: null }` |
| `consentkit:change` | wie oben, `action` ist `accept_all`, `reject_all`, `custom`, `gpc` oder `embed` |

### Elemente

`<consent-kit>` – Attribute `layout` (`box`, `bar`, `modal`), `position` (`bottom-left`, `bottom-right`, `top-left`, `top-right`), `theme` (`light`, `dark`, `auto`). Methoden `open('banner' | 'settings')`, `close()`.

`<consent-embed>` – Attribute `service` (Schlüssel), `label` (Beschriftung), `theme`. Inhalt: ein `<template>` mit dem Original-Markup. Nach dem Laden trägt das Element das Attribut `loaded`.

### Markup-Konventionen

| Markup | Wirkung |
| --- | --- |
| `<script type="text/plain" data-consent="key">` | läuft nach Einwilligung; `data-src` für externe Scripts, `data-type="module"` für Module |
| `href="#consent-kit"`, `data-consent-kit-open`, `.consent-kit-open`, `.consent_manager-show-box` | öffnet die Einstellungen |

## Cookie

Name `consent_kit`, Wert URL-kodiertes JSON:

```json
{
  "id": "0b8f7a52-…",
  "e": 0,
  "rev": 12,
  "ts": 1790018320,
  "a": { "matomo": "7cb02e" },
  "r": { "youtube": "0a566f" }
}
```

| Feld | Bedeutung |
| --- | --- |
| `id` | Einwilligungs-ID |
| `e` | Epoche; steigt mit „Alle Besucher erneut fragen“ und macht ältere Cookies ungültig |
| `rev` | Stand zum Zeitpunkt der Entscheidung |
| `ts` | Unix-Zeit der Entscheidung |
| `a` / `r` | akzeptierte / abgelehnte Dienste mit dem Fingerabdruck ihrer damaligen Fassung |

Weicht der Fingerabdruck eines Dienstes vom aktuellen ab, gilt die Entscheidung für diesen Dienst nicht mehr und er wird erneut abgefragt.

## Endpoint

`POST index.php?rex-api-call=consent_kit`, JSON-Body:

```json
{ "id": "…", "action": "custom", "accepted": ["matomo"], "gpc": false, "lang": "de" }
```

Der Server übernimmt nur Schlüssel, die auf der aktuellen Domain aktiv und optional sind, schreibt das Protokoll, setzt den Cookie per Header und antwortet mit `{ "ok": true, "state": { … } }`. Anfragen, die der Browser nicht als `same-origin` kennzeichnet (`Sec-Fetch-Site`), werden abgewiesen.

## Tabellen

| Tabelle | Inhalt |
| --- | --- |
| `rex_consent_kit_group` | Gruppen (`key`, `prio`, `required`, `name`, `description`) |
| `rex_consent_kit_service` | Dienste inkl. Code-Felder, `params`, `gcm_signals`, `embed_hosts`, `domain_ids` (leer = alle), `preset` |
| `rex_consent_kit_item` | Cookies und Speichereinträge je Dienst |
| `rex_consent_kit_variant` | Abweichungen je `domain_id` (0 = alle) und `clang` (leer = alle) |
| `rex_consent_kit_domain` | Domains mit Rechtstexten; `host = '*'` ist der Fallback |
| `rex_consent_kit_revision` | Stände mit Schnappschuss |
| `rex_consent_kit_log` | Protokoll |
| `rex_consent_kit_catalog` | optional geladene Open Cookie Database |

Übersetzbare Felder (`name`, `description`, `purpose`) sind JSON mit dem Sprachcode als Schlüssel: `{"de": "…", "en": "…"}`. Fehlt eine Sprache, greift der Sprachanteil (`de` für `de_at`), dann die Standardsprache, dann Englisch.

## Vorlagen-Format

Dateien: `presets/*.json` im AddOn und `…/data/addons/consent_kit/presets/*.json` (update-sicher, überschreibt gleiche Schlüssel).

```json
{
  "services": [
    {
      "key": "my_chat",
      "name": "Mein Chat",
      "group": "functional",
      "provider": "Beispiel GmbH, Musterweg 1, 12345 Musterstadt",
      "privacy_url": "https://example.com/datenschutz",
      "description": { "de": "Chat-Fenster für Support-Anfragen.", "en": "Chat window for support requests." },
      "params": [
        { "key": "widget_id", "label": { "de": "Widget-ID", "en": "Widget ID" }, "placeholder": "abc123", "pattern": "^[a-z0-9]+$" }
      ],
      "html_body": "<script src=\"https://chat.example.com/w/{{widget_id}}.js\" data-lang=\"{{lang}}\"></script>",
      "items": [
        { "type": "cookie", "name": "chat_session", "host": "", "duration": { "value": 30, "unit": "days" }, "purpose": { "de": "Erkennt die Chat-Sitzung wieder.", "en": "Recognises the chat session." } }
      ],
      "embed_hosts": [],
      "gcm_signals": [],
      "sources": ["https://example.com/docs/cookies"],
      "verified": "2026-09-21"
    }
  ]
}
```

Alle Felder beschreibt `presets/FORMAT.md`.
