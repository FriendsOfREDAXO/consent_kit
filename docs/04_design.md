# Gestaltung

Die Komponente ist bewusst neutral: Sie übernimmt die Schrift der Seite, bringt zurückhaltende Farben mit und lässt sich über wenige Variablen an jedes Design anpassen. Das Shadow DOM schützt sie vor dem CSS der Website – und umgekehrt.

## Design-Editor

**Design** zeigt links die Variablen – Farben hell und dunkel, Schrift, Abstände und Breiten, Form, Schaltflächen –, rechts eine Live-Vorschau mit den echten Diensten: Hinweis, Einstellungen und Platzhalter, hell und dunkel, Desktop und Mobil. Eine Kontrastprüfung meldet Kombinationen unter WCAG AA (4,5 : 1 für Text, 3 : 1 für Rahmen). Gespeichert werden nur Abweichungen vom Standard.

Wer die Werte lieber im eigenen Stylesheet pflegt, kopiert den Block unter „Als CSS für das eigene Stylesheet“.

## Variablen

```css
consent-kit,
consent-embed {
    --ck-accent: #0a7d5a;
    --ck-button-bg: #0a7d5a;
    --ck-button-border: #0a7d5a;
    --ck-radius: 4px;
}
```

| Variable | Standard hell | Dunkel (`--ck-dark-…`) | Wirkung |
| --- | --- | --- | --- |
| `--ck-bg` | `#ffffff` | `#18181b` | Hintergrund |
| `--ck-text` | `#1a1a1a` | `#f4f4f5` | Text |
| `--ck-muted` | `#595959` | `#b4b4bb` | Nebentext |
| `--ck-border` | `#8c8c8c` | `#8e8e96` | Rahmen, Schalter aus |
| `--ck-line` | `#d9d9d9` | `#3f3f46` | Trennlinien |
| `--ck-accent` | `#1d4ed8` | `#93c5fd` | Links, Schalter an, Fokusrahmen |
| `--ck-button-bg` | `#1f2937` | `#f4f4f5` | Schaltflächen |
| `--ck-button-text` | `#ffffff` | `#18181b` | Schaltflächen-Text |
| `--ck-button-border` | `#1f2937` | `#f4f4f5` | Schaltflächen-Rahmen |
| `--ck-shadow` | weicher Schatten | kräftiger | Schatten des Dialogs |
| `--ck-backdrop` | `rgba(0,0,0,.55)` | `rgba(0,0,0,.7)` | Abdunklung hinter dem Dialog |

Die Hover-Farben der Schaltflächen gibt es ebenfalls hell und dunkel. Ohne eigene Werte bleibt es bei der Helligkeitsänderung des Standards:

| Variable | Standard | Wirkung |
| --- | --- | --- |
| `--ck-button-hover-bg` | wie `--ck-button-bg` | Schaltfläche im Hover |
| `--ck-button-hover-text` | wie `--ck-button-text` | Text im Hover |
| `--ck-button-hover-border` | wie `--ck-button-border` | Rahmen im Hover |
| `--ck-button-hover-filter` | `brightness(1.15)` | Aufhellung; `none` schaltet sie ab |

Ohne dunkle Entsprechung:

| Variable | Standard | Wirkung |
| --- | --- | --- |
| `--ck-font` | `inherit` | Schriftart |
| `--ck-font-size` | `1rem` | Schriftgröße |
| `--ck-line-height` | `1.5` | Zeilenhöhe |
| `--ck-heading-size` | `1.2em` | Größe der Überschrift |
| `--ck-heading-weight` | `700` | Stärke der Überschrift |
| `--ck-small-size` | `.875em` | Größe von Nebentext, Tabellen und Details |
| `--ck-space` | `1.25rem` | Innenabstand von Kopf, Inhalt und Fuß |
| `--ck-gap` | `.6rem` | Abstand zwischen Schaltflächen und Gruppen |
| `--ck-radius` | `12px` | Ecken von Dialog und Platzhalter |
| `--ck-button-radius` | `8px` | Ecken der Schaltflächen |
| `--ck-group-radius` | `10px` | Ecken von Gruppen, Tabellen und Hinweisen |
| `--ck-border-width` | `1px` | Rahmen von Dialog, Gruppen und Tabellen |
| `--ck-button-padding` | `.55rem 1rem` | Innenabstand der Schaltflächen |
| `--ck-button-weight` | `600` | Schriftstärke der Schaltflächen |
| `--ck-button-border-width` | `2px` | Rahmenbreite der Schaltflächen |
| `--ck-button-transform` | `none` | z. B. `uppercase` |
| `--ck-button-letter-spacing` | `normal` | Laufweite der Schaltflächen |
| `--ck-switch-width` | `2.75rem` | Breite der Schalter |
| `--ck-switch-height` | `1.5rem` | Höhe der Schalter; der Knopf richtet sich danach |
| `--ck-tap-size` | `2.75rem` | Mindestgröße aller Bedienelemente (44 px, nicht kleiner setzen) |
| `--ck-width` | `30rem` | Breite von Box und Dialog |
| `--ck-settings-width` | `44rem` | Breite des Einstellungen-Dialogs |
| `--ck-backdrop-filter` | `none` | z. B. `blur(4px)` hinter dem Dialog |
| `--ck-trigger-shadow` | weicher Schatten | Schatten der schwebenden Schaltfläche |
| `--ck-z` | `2147483000` | Stapelreihenfolge |
| `--ck-embed-ratio` | `16 / 9` | Seitenverhältnis des Platzhalters |
| `--ck-embed-min-height` | `14rem` | Mindesthöhe des Platzhalters |

## Eigenes Stylesheet

Was sich mit Variablen nicht erreichen lässt, geht über eine eigene CSS-Datei: **Design › Eigenes Stylesheet**. Die Datei wird zusätzlich im Shadow DOM geladen und erreicht damit auch Elemente, für die es keine Variable gibt.

```css
/* /assets/consent-kit.css – projektübergreifend wiederverwendbar */
.btn { letter-spacing: .05em; }
.group { border-style: dashed; }
caption { text-transform: uppercase; }
```

Angegeben wird ein projektinterner Pfad (`/assets/consent-kit.css`) oder eine vollständige Adresse. Zwei Dinge sind dabei zu beachten: Eine Datei auf fremder Domain muss dort erreichbar und in der `Content-Security-Policy` der Website erlaubt sein. Und das eigene Stylesheet steht hinter dem Basis-CSS – bei gleicher Spezifität gewinnt es, `!important` ist selten nötig.

Wer nur Variablen setzen will, braucht dafür kein Feld im Backend: `--ck-*` sind vererbte Custom Properties und wirken deshalb auch aus einem ganz normalen Stylesheet der Website heraus durch die Shadow-Grenze.

```css
consent-kit, consent-embed { --ck-accent: #1e87f0; }
```

Der Dunkelmodus wird über das Attribut `theme` gesteuert (`light`, `dark`, `auto`), nicht über eigene Media Queries: `auto` folgt `prefers-color-scheme`.

## Parts

Für alles, was über Variablen hinausgeht:

| Part | Element |
| --- | --- |
| `dialog` | Hinweis bzw. Einstellungen |
| `button` | **jede** Schaltfläche |
| `close` | das × zum Schließen ohne Entscheidung |
| `withdraw` | Link „Einwilligung widerrufen“ im Einstellungen-Dialog |
| `group` | Gruppe im Einstellungen-Dialog |
| `service` | Dienst innerhalb einer Gruppe |
| `trigger` | schwebende Schaltfläche |
| `placeholder` | Platzhalter von `<consent-embed>` |

```css
consent-kit::part(dialog) { border-width: 2px; }
consent-kit::part(button) { text-transform: uppercase; letter-spacing: .04em; }
consent-kit::part(trigger) { bottom: 5rem; }
```

### Warum es nur ein `button`-Part gibt

„Ablehnen“ muss so leicht erreichbar und so auffällig sein wie „Akzeptieren“. Alle Schaltflächen teilen sich deshalb Klasse und Part – eine einzelne hervorzuheben ist absichtlich nicht vorgesehen. Je nach Platz stehen alle nebeneinander oder alle untereinander; nie steht eine allein in einer Zeile.

## Barrierefreiheit

- Natives `<dialog>`: Fokusfalle, Escape und „Rest der Seite inert“ kommen vom Browser. Escape und das × schließen ohne Entscheidung – nichts wird geladen, der Hinweis bleibt für die Browser-Sitzung ausgeblendet.
- Box und Leiste ziehen beim Seitenaufruf **keinen** Fokus an sich, sind aber der erste Tab-Stopp. Dialog und Einstellungen setzen den Fokus auf die Überschrift und geben ihn beim Schließen zurück.
- Schalter sind echte Checkboxen mit Beschriftung, Gruppen zeigen einen gemischten Zustand.
- Auf schmalen Bildschirmen wird die Cookie-Tabelle zur Liste, es gibt kein horizontales Scrollen.
- Animationen nur ohne `prefers-reduced-motion`; `forced-colors` (Windows-Kontrastmodus) wird unterstützt.
- Mindestgröße der Bedienelemente 44 × 44 px.
