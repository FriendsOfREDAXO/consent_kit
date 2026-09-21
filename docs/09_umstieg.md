# Umstieg vom consent_manager

Beide AddOns können parallel installiert sein. Das erlaubt einen Umstieg ohne Lücke: Consent Kit einrichten und prüfen, dann im consent_manager die Ausgabe abschalten bzw. `REX_CONSENT_MANAGER[]` aus dem Template nehmen.

## Übernahme

**Werkzeuge → Übernahme aus „consent_manager“** liest

- direkt aus den Tabellen des alten AddOns, wenn es in derselben Installation liegt, oder
- aus einem JSON-Export des alten AddOns.

| Alt | Neu |
| --- | --- |
| Cookie-Gruppe (`required`, `statistics`, `marketing`, `external`, `services`) | Gruppe (`necessary`, `statistics`, `marketing`, `media`, `functional`); andere Schlüssel werden als neue Gruppen angelegt |
| „Cookie“ (= Dienst) je Sprache | **ein** Dienst, Beschreibungen je Sprache zusammengeführt |
| YAML-Definition (`name`, `time`, `desc`) | Einträge unter „Cookies & Speicher“ |
| Script | „HTML im `<head>`“ |
| Script bei Abwahl | „JavaScript bei Widerruf“, wenn es genau ein Inline-Script ist |
| Domain mit Datenschutz-/Impressum-Artikel | Domain |

Bewusst **nicht** übernommen werden Texte, Themes, der Cookie des alten AddOns und Gruppen-Scripts (dafür einen eigenen Dienst anlegen).

Übernommene Dienste starten **inaktiv**. Nach der Übernahme zeigt ein Bericht, was zu prüfen ist – vor allem Laufzeiten: Der consent_manager speichert sie als Freitext („14 Tage / 1 Jahr“). Was sich nicht sicher umrechnen lässt, wird als „Sitzung“ übernommen und die Originalangabe an den Zweck angehängt. Vorhandene Schlüssel werden nie überschrieben.

Oft ist es sauberer, Standarddienste (Matomo, YouTube, Google Analytics …) frisch aus den geprüften Vorlagen anzulegen und nur Eigenentwicklungen zu übernehmen.

## Templates und Module anpassen

| consent_manager | Consent Kit |
| --- | --- |
| `REX_CONSENT_MANAGER[]` | entfällt (automatische Einbindung) oder `REX_CONSENT_KIT[]` |
| `consent_manager_util::has_consent('uid')` / `Utility::has_consent('uid')` | `\KLXM\ConsentKit\Consent::has('key')` |
| `doConsent('youtube', $html)` / `InlineConsent::doConsent()` | `\KLXM\ConsentKit\Consent::embed('youtube', $html)` |
| `Frontend::getCookieList()` / `REX_COOKIEDB[]` | `REX_CONSENT_KIT[output=overview]` |
| Klasse `consent_manager-show-box` | funktioniert weiter; neu: `href="#consent-kit"` |
| JS-Ereignis `consent_manager-saved` | `consentkit:change` auf `document` |
| Cookie `consentmanager` | `consent_kit` – Besucher werden einmal neu gefragt |

Schlüssel werden bei der Übernahme auf Kleinbuchstaben, Ziffern und Unterstriche normalisiert (`google-analytics` → `google_analytics`). Aufrufe in Templates entsprechend anpassen.
