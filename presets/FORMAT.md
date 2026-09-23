# Preset format

Every `presets/*.json` file holds a list of service presets. All files are merged by `PresetRepository`.

```json
{
  "services": [
    {
      "key": "google_analytics",
      "name": "Google Analytics 4",
      "group": "statistics",
      "provider": "Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Ireland",
      "privacy_url": "https://policies.google.com/privacy",
      "description": {
        "de": "Ein bis zwei neutrale Sätze: was der Dienst tut und wozu Daten verarbeitet werden.",
        "en": "One or two neutral sentences."
      },
      "params": [
        {
          "key": "measurement_id",
          "label": {"de": "Mess-ID", "en": "Measurement ID"},
          "placeholder": "G-XXXXXXXXXX",
          "pattern": "^G-[A-Z0-9]+$"
        }
      ],
      "html_head": "<script async src=\"https://www.googletagmanager.com/gtag/js?id={{measurement_id}}\"></script>",
      "html_body": "",
      "js_default": "",
      "js_accept": "",
      "js_revoke": "",
      "gcm_signals": ["analytics_storage"],
      "embed_hosts": [],
      "items": [
        {
          "type": "cookie",
          "name": "_ga",
          "host": "",
          "duration": {"value": 2, "unit": "years"},
          "purpose": {"de": "Unterscheidet Besucher.", "en": "Distinguishes visitors."}
        }
      ],
      "sources": ["https://…official vendor documentation URL…"],
      "verified": "2026-09-21"
    }
  ]
}
```

## Fields

- `key`: `[a-z0-9_]+`, unique across all preset files.
- `group`: one of `necessary`, `functional`, `statistics`, `marketing`, `media`.
- `params`: values the site owner must enter; referenced as `{{key}}` in the code fields and in `events`. `pattern` is an optional JS/PCRE-compatible regex used as the input's `pattern` attribute. Empty array if none. See **Placeholders** below.
- `html_head` / `html_body`: HTML (usually `<script>` tags) injected only after consent. Use the vendor's current official snippet.
- `js_default`: plain JS that runs on every page before any consent decision (e.g. a vendor "consent default denied" call). Must not load anything or set cookies.
- `js_accept`: plain JS run each time the service is (or already was) accepted, after `html_*` was injected.
- `js_revoke`: plain JS run when consent is withdrawn (vendor revoke call).
- `gcm_signals`: Google Consent Mode v2 consent types this service needs (`ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, `functionality_storage`, `personalization_storage`, `security_storage`). Only for Google tags.
- `embed_hosts`: host names (no scheme, no path) whose iframes belong to this service, e.g. `youtube.com`, `youtube-nocookie.com`. Subdomains match automatically.
- `items[].type`: `cookie`, `local_storage`, `session_storage`, `indexed_db`.
- `items[].name`: exact name; `*` allowed as wildcard (`_ga_*`).
- `items[].host`: domain that sets the entry if it is a third-party host (e.g. `.youtube.com`), empty string for first-party.
- `items[].duration.unit`: `session`, `minutes`, `hours`, `days`, `months`, `years`, `persistent` (`value` is `0` for `session`/`persistent`).
- `events` (optional): `{"lead": "…", "registration": "…", "appointment": "…", "page_view": "…"}` – the vendor call per conversion event, used by the “Events” tab. `{{label}}` is the per-event identifier the site owner enters (e.g. a Google Ads conversion label); `{{param}}` placeholders work as in the code fields.
- `note` (optional): `{"de": "…", "en": "…"}` – what could not be verified against vendor documentation; shown to the admin in the service form.
- `sources`: official vendor documentation URLs that back the items and snippets.

## Placeholders

A preset carries the vendor's code but never an account identifier. Wherever an
ID belongs, the code holds `{{key}}` and `params` describes the field the site
owner fills in:

```json
"params": [
  { "key": "measurement_id", "label": {"de": "Mess-ID", "en": "Measurement ID"},
    "placeholder": "G-XXXXXXXXXX", "pattern": "^G-[A-Z0-9]+$" }
],
"html_head": "<script async src=\"https://www.googletagmanager.com/gtag/js?id={{measurement_id}}\"></script>"
```

`label` becomes the field label, `placeholder` the greyed-out example, `pattern`
the browser-side format check. Substitution happens when the configuration is
cached; a placeholder left unresolved means the service is incomplete and is not
delivered at all.

Two placeholders are always available and need no `params` entry: `{{lang}}`
(two-letter language code) and `{{domain}}` (current host). Inside `events`,
`{{label}}` is the per-event identifier entered by the site owner.

## Export and import

The backend writes and reads these files under **Tools → Own templates**. An
export of a configured service keeps the placeholders and the `params`
definitions but never the entered values, and leaves out domains, variants,
status and order. Own template files live in
`…/data/addons/consent_kit/presets/*.json`, survive updates and override
bundled presets with the same key.
