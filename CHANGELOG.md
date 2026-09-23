# Changelog

## Unveröffentlicht

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
