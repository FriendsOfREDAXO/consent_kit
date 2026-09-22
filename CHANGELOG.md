# Changelog

## 1.0.0-dev

- `<oembed>`-Tags von CKEditor 5 und TinyMCE (YouTube, Vimeo, Hosts der Dienste) werden automatisch in gesperrte Platzhalter umgewandelt; `Consent::oembed()`, `Consent::head()`.
- Schließen-Schaltfläche (×) und Escape: schließen ohne Entscheidung, für die Browser-Sitzung gemerkt; abschaltbar. Warnung, wenn eine Startseite als Datenschutzerklärung/Impressum gewählt ist. Einstellungen bleiben unangetastet, wenn ein veraltetes Formular gespeichert wird.
- Usability-Runde: Dienste × Domains als kompakte Matrix, Varianten je Domain/Sprache, Fokus und Formatprüfung bei Vorlagen-IDs, bedingte Einstellungen, Einstieg für Erstnutzer, Mobilansicht ohne horizontales Scrollen.
- Vorlagen „Google Analytics 4 / Google Ads (über Tag Manager)“, eigene Vorlagen update-sicher im Data-Ordner.
- `Consent::overview()` bzw. `REX_CONSENT_KIT[output=overview]`, Extension Points `CONSENT_KIT_CONFIG` und `CONSENT_KIT_SAVED`.
- Dokumentation in zehn Kapiteln mit eigener Hilfe-Seite.
- Erste Fassung: Web Components `<consent-kit>` und `<consent-embed>`, Dienste × Domains, Varianten je Domain/Sprache, 37 geprüfte Vorlagen (de/en), Google Consent Mode v2, Global Privacy Control, Protokoll mit Revisionen, Design-Editor mit Live-Vorschau, Cookie-Scanner, Übernahme aus `consent_manager`.
