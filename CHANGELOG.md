# Changelog

## Unveröffentlicht

- Platzhalter: `{privacy}`, `{imprint}` und `{service_privacy}` im Text werden zu Links auf die eigene Datenschutzerklärung, das Impressum bzw. die Datenschutzerklärung des Dienstes; der übrige Text bleibt maskiert (#7).
- Platzhalter für Dienste, die nicht angelegt, inaktiv oder der Domain nicht zugeordnet sind: kein „Inhalt einmal laden“ mehr, sondern „Dieser Inhalt ist derzeit nicht verfügbar“. Der Name kommt aus der gleichnamigen Vorlage statt des rohen Schlüssels; angemeldete Redakteure sehen, welcher Dienst fehlt, die Konsole meldet es ebenfalls (#4).
- `ConsentKit.accept()` für eigene 2-Klick-Lösungen (#2), `--ck-rem` für Seiten mit verkleinerter Grundschrift (#3), Cache-Buster für das eigene Stylesheet (#6).
- Behoben: Vorlagensuche und Filter unter „Dienst hinzufügen“ blendeten nichts aus (#1); der Platzhalter wurde auf schmalen Schirmen abgeschnitten (#5).

## 1.0.0-beta3 – 2026-09-23

- **Eigene Vorlagen importieren und exportieren** unter **Werkzeuge → Eigene Vorlagen**: angelegte Dienste als Vorlagen-Datei herunterladen (alle oder eine Auswahl, einzeln auch im Dienst-Formular) und fremde Vorlagen einspielen. Der Export enthält Code, Cookies und die auszufüllenden Felder, aber nie die eingetragenen IDs, Domains, Varianten oder den Status – die Platzhalter `{{…}}` bleiben im Code stehen. Der Import prüft Schlüssel, Gruppe, Eintragsarten, Laufzeiten und Consent-Mode-Signale und benennt fehlerhafte Einträge einzeln. Vorhandene eigene Vorlagen lassen sich dort auch herunterladen und löschen.
- Behoben: Nach einem REDAXO-Cache-Reset brach die nächste Aktion, die den Konfigurations-Cache leert, mit einer `UnexpectedValueException` ab („Failed to open directory“) – der Ordner `var/cache/addons/consent_kit/` existierte dann nicht mehr. Betroffen waren alle Speichervorgänge sowie die Sammelübersetzung.
- Neues Kapitel **Eigene Vorlagen** in der Hilfe: wie Platzhalter (`params`, `{{measurement_id}}`, `{{lang}}`, `{{domain}}`), `placeholder` und `pattern` zusammenspielen, was beim Export draußen bleibt und worauf beim Weitergeben zu achten ist.

## 1.0.0-beta2 – 2026-09-23

- Neue Form des Hinweises: **Off-Canvas** – ein Panel über die volle Höhe am linken oder rechten Rand, wie bei Box und Leiste ohne Abdunklung, die Seite bleibt bedienbar. Breite über `--ck-offcanvas-width`, auf schmalen Schirmen volle Breite; das Einfahren entfällt bei `prefers-reduced-motion`.
- Option **Gruppen im Hinweis zeigen** (nur bei Dialog und Off-Canvas): Der Hinweis listet die Gruppen mit Schaltern, die mittlere Schaltfläche wird „Auswahl speichern“. Es ist bewusst nichts vorausgewählt – eine Vorbelegung wäre keine wirksame Einwilligung. Die einzelnen Dienste bleiben in den Einstellungen.
- Die Vorschau im Design-Editor hat einen Umschalter für die Form (Box, Leiste, Dialog, Off-Canvas). Damit lassen sich die Formen durchprobieren, ohne die gespeicherte Einstellung zu ändern.
- Behoben: Im Hinweis mit Gruppen liessen sich die Schalter nicht bedienen – der Zustand wurde aus den (dort nicht vorhandenen) Dienst-Schaltern abgeleitet und sofort wieder zurueckgesetzt.
- Die Einstellungen behalten die Off-Canvas-Form: Wer den Hinweis seitlich einfahren laesst, bekommt auch beim erneuten Aufruf ein Panel an derselben Seite statt eines mittigen Dialogs. Breite optional ueber `--ck-offcanvas-settings-width`.
- Behoben: Bei langem Inhalt konnte der Textbereich des Hinweises die Schaltflächen aus dem Dialog schieben, sodass „Akzeptieren“ unter dem sichtbaren Bereich lag. Jetzt scrollt der Inhalt, die Schaltflächen bleiben immer vollständig sichtbar.
- Gestaltung deutlich erweitert: Abstände, Zeilenhöhe, Überschriften- und Nebentextgröße, Innenabstand, Schriftstärke, Rahmenbreite, Schreibweise und Hover-Farben der Schaltflächen, Größe der Schalter, Eckenradius von Gruppen und Tabellen, Backdrop-Filter, Mindesthöhe des Platzhalters. Alle neuen Variablen behalten als Standard exakt den bisherigen Wert, die Darstellung ändert sich also nicht von selbst.
- Eigenes Stylesheet unter **Design**: Eine CSS-Datei (Projektpfad oder vollständige Adresse) wird zusätzlich im Shadow DOM geladen und erreicht damit auch Elemente ohne eigene Variable – projektübergreifend wiederverwendbar.
- Design-Editor nach Themen gegliedert (Farben hell/dunkel, Schrift, Abstände und Breiten, Form, Schaltflächen); die Kontrastprüfung berücksichtigt auch die Hover-Farben.
- Die Schaltflächen bleiben bewusst gemeinsam gestaltet: Es gibt weiterhin keine Variable, mit der sich „Akzeptieren“ gegenüber „Ablehnen“ hervorheben ließe.

## 1.0.0-beta1 – 2026-09-22

Erste öffentliche Beta. Rückmeldungen bitte über die GitHub-Issues.

- Reiter „Ereignisse“ je Dienst: Conversions (Anfrage, Registrierung, Terminbuchung, Seite) per Klick/Seite/Formular ohne Code; Aufrufe für OpenAI Pixel, Meta Pixel, Google Ads, GA4 und Matomo in den Vorlagen.
- Vorlage „OpenAI Measurement Pixel (ChatGPT Ads)“ mit Consent-Aufrufen; Anleitung zum Conversion-Tracking in den Tipps.
- Cookie-Scanner zeigt die tatsächliche Laufzeit aus dem Browser neben der dokumentierten und markiert Abweichungen.
- Schlüssel von Diensten und Gruppen sind nach dem Anlegen gesperrt (Freigabe mit Rückfrage).
- „Einwilligung widerrufen“ im Einstellungen-Dialog (Cookie löschen, Dienste stoppen, Protokoll „Widerrufen“), `ConsentKit.withdraw()`.
- Sprachfelder per WriteAssist aus der Standardsprache übersetzen (wenn installiert und konfiguriert): einzeln, alle leeren Felder eines Formulars oder unter Werkzeuge alle fehlenden Übersetzungen einer Sprache.
- `<oembed>`-Tags von CKEditor 5 und TinyMCE (YouTube, Vimeo, Hosts der Dienste) werden automatisch in gesperrte Platzhalter umgewandelt; `Consent::oembed()`, `Consent::head()`.
- Schließen-Schaltfläche (×) und Escape: schließen ohne Entscheidung, für die Browser-Sitzung gemerkt; abschaltbar. Warnung, wenn eine Startseite als Datenschutzerklärung/Impressum gewählt ist. Einstellungen bleiben unangetastet, wenn ein veraltetes Formular gespeichert wird.
- Usability-Runde: Dienste × Domains als kompakte Matrix, Varianten je Domain/Sprache, Fokus und Formatprüfung bei Vorlagen-IDs, bedingte Einstellungen, Einstieg für Erstnutzer, Mobilansicht ohne horizontales Scrollen.
- Vorlagen „Google Analytics 4 / Google Ads (über Tag Manager)“, eigene Vorlagen update-sicher im Data-Ordner.
- `Consent::overview()` bzw. `REX_CONSENT_KIT[output=overview]`, Extension Points `CONSENT_KIT_CONFIG` und `CONSENT_KIT_SAVED`.
- Dokumentation in elf Kapiteln mit eigener Hilfe-Seite, darunter „Conversions und Ereignisse“.
- Erste Fassung: Web Components `<consent-kit>` und `<consent-embed>`, Dienste × Domains, Varianten je Domain/Sprache, 38 geprüfte Vorlagen (de/en), Google Consent Mode v2, Global Privacy Control, Protokoll mit Revisionen, Design-Editor mit Live-Vorschau, Cookie-Scanner, Übernahme aus `consent_manager`.
