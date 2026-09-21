# Consent Mode und Signale

## Google Consent Mode v2

Der Consent Mode schaltet sich automatisch ein, sobald ein aktiver Dienst Signale nutzt (*Dienst → Erweitert*). Die Vorlagen bringen die Zuordnung mit: Google Analytics `analytics_storage`, Google Ads `ad_storage`, `ad_user_data`, `ad_personalization`.

Ablauf:

1. Ganz oben im `<head>`, vor jedem Tag:
   `gtag('consent', 'default', { ad_storage: 'denied', …, security_storage: 'granted', wait_for_update: 500 })`
2. Liegt bereits eine Entscheidung vor, folgt im selben Script sofort `gtag('consent', 'update', …)` – ohne auf die Komponente zu warten.
3. Bei jeder Entscheidung: `update`. Ein Signal ist `granted`, sobald **ein** akzeptierter Dienst es nutzt.

Das AddOn arbeitet im **Basic Mode**: Google-Tags werden erst nach Einwilligung geladen. Der Advanced Mode (Tags laden sofort und senden cookielose Pings) ist in Deutschland umstritten und wird nicht angeboten.

Feineinstellungen unter **Einstellungen → Google Consent Mode v2**:

| Option | Bedeutung |
| --- | --- |
| `ads_data_redaction` | entfernt Klick-Kennungen, solange `ad_storage` abgelehnt ist (Standard: an) |
| `url_passthrough` | reicht `gclid` u. a. über Link-Parameter weiter, wenn Cookies abgelehnt sind (Standard: aus) |
| `wait_for_update` | so lange warten Google-Tags auf die Entscheidung (Standard: 500 ms) |

### Google Tag Manager

1. Vorlage **Google Tag Manager** hinzufügen, Container-ID eintragen. Der Container wird erst nach Einwilligung in diesen Dienst geladen.
2. Für jedes Google-Tag im Container die passende Vorlage **„… (über Tag Manager)“** hinzufügen (Google Analytics 4, Google Ads). Diese Vorlagen laden selbst nichts, bringen aber Cookies, Beschreibung und die Consent-Mode-Signale mit – erst dadurch schaltet sich der Consent Mode ein und Besucher können jedes Tag einzeln wählen.
3. Im GTM die Einwilligungsprüfung der Tags aktivieren; sie liest den Consent Mode.

In welche Gruppe der Tag Manager selbst gehört, ist eine rechtliche Bewertung: Er setzt laut Google selbst keine Cookies, überträgt beim Laden aber die IP-Adresse. Die Vorlage ordnet ihn vorsichtshalber „Marketing“ zu; die Gruppe lässt sich im Dienst ändern.

Zusätzlich landen zwei Ereignisse im `dataLayer`:

| Ereignis | Wann |
| --- | --- |
| `consentkit_ready` | Komponente gestartet |
| `consentkit_change` | Entscheidung getroffen oder geändert |

Beide tragen `consentkit: { accepted: […], rejected: […], action: … }`. Die Ereignisse erscheinen nur bei aktivem Consent Mode.

Soll ein Tag von einem Dienst abhängen, den Google nicht kennt, legen Sie den Dienst im AddOn an (ohne Script) und prüfen im GTM per benutzerdefiniertem Ereignis `consentkit_change` und einer Variable auf `consentkit.accepted`.

## Global Privacy Control

GPC ist ein Browser-Signal (`navigator.globalPrivacyControl`, Header `Sec-GPC`), mit dem Besucher Tracking generell widersprechen. Firefox, Brave und DuckDuckGo haben es eingebaut. In der EU ist es rechtlich nicht bindend, lässt sich aber als Widerspruch werten.

| Verhalten | Wirkung |
| --- | --- |
| **Als Ablehnung werten** (Standard) | Optionale Dienste bleiben aus, es erscheint kein Hinweis, im Protokoll steht „Abgelehnt per GPC“. Über die Cookie-Einstellungen können Besucher trotzdem einzeln zustimmen – eine ausdrückliche Einwilligung geht dem Signal vor. |
| Trotzdem fragen | Der Hinweis erscheint mit einer Anmerkung zum Signal. |
| Ignorieren | Das Signal wird weder ausgewertet noch protokolliert. |

„Do Not Track“ ist eingestellt und wird nicht ausgewertet. Für die „anerkannten Dienste zur Einwilligungsverwaltung“ nach § 26 TDDDG gibt es bislang keine standardisierte technische Schnittstelle.

## Aufrufe anderer Anbieter

Andere Anbieter haben eigene Consent-Schnittstellen. Die Vorlagen nutzen dafür die drei JavaScript-Felder:

| Anbieter | vor jeder Entscheidung | bei Einwilligung | bei Widerruf |
| --- | --- | --- | --- |
| Microsoft Advertising (UET) | `uetq.push('consent','default',{ad_storage:'denied'})` | `…'update',{ad_storage:'granted'}` | `…'update',{ad_storage:'denied'}` |
| Microsoft Clarity | – | `clarity('consentv2', { ad_Storage: 'denied', analytics_Storage: 'granted' })` | derselbe Aufruf mit beiden Werten `denied` |
| Meta Pixel | – | `fbq('consent','grant')` | `fbq('consent','revoke')` |

Was eine Vorlage konkret enthält, steht im Reiter **Scripts** des Dienstes. Für TikTok ließ sich kein Consent-Aufruf in der Anbieterdokumentation belegen; dort wird schlicht das Script blockiert.

## Nicht unterstützt: IAB TCF

Das Transparency & Consent Framework braucht nur, wer programmatische Werbung ausspielt (AdSense/Ad Manager mit personalisierten Anzeigen). Es setzt eine kostenpflichtige CMP-Registrierung bei IAB Europe voraus und ist deshalb bewusst kein Bestandteil.
