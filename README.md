# Consent Kit

Einwilligungsverwaltung für REDAXO: eine neutrale, barrierefreie Web Component im Frontend, ein kompaktes Backend und ein nachvollziehbares Protokoll.

## Was es kann

- **Dienste einzeln wählbar**, in Gruppen zusammengefasst; „Alle ablehnen“ und „Alle akzeptieren“ sind technisch immer gleichwertig gestaltet.
- **38 Vorlagen** (Deutsch/Englisch) mit Scripts, Cookies, Storage-Einträgen, Laufzeiten und Quellenangabe – geprüft gegen die Dokumentation der Anbieter.
- **Dienste × Domains** als Matrix, **Varianten** je Domain oder Sprache (andere IDs, anderer Code).
- **Google Consent Mode v2**, **Global Privacy Control**, Anbieter-Aufrufe für Microsoft UET/Clarity, Meta und weitere.
- **Conversions ohne Code:** „Wenn Klick auf … dann melde Anfrage“ – der Aufruf je Anbieter kommt aus der Vorlage.
- **2-Klick-Platzhalter** für Videos, Karten und Social-Media-Einbettungen; `<oembed>`-Tags aus CKEditor 5 und TinyMCE werden automatisch gesperrt.
- **Protokoll** ohne IP-Adresse und User-Agent, mit Schnappschuss dessen, was zur Auswahl stand.
- **Design-Editor** mit Live-Vorschau, Dark Mode und Kontrastprüfung; Gestaltung über CSS Custom Properties.
- **Barrierefrei:** natives `<dialog>`, Fokusführung, Tastaturbedienung, `prefers-reduced-motion`, `forced-colors`.

## In fünf Minuten

1. AddOn installieren. Der Hinweis wird automatisch auf allen Seiten eingebunden.
2. **Einstellungen → Rechtstexte und Domains:** Datenschutzerklärung und Impressum wählen. Domains aus YRewrite erscheinen von selbst.
3. **Dienste → Dienst hinzufügen:** Vorlage wählen, ID eintragen, speichern.

Solange kein einwilligungspflichtiger Dienst aktiv ist, sehen Besucher keinen Hinweis.

## Kapitel

1. [Einrichtung](docs/01_einrichtung.md)
2. [Dienste, Domains, Varianten](docs/02_dienste.md)
3. [Einbindung im Template](docs/03_einbindung.md)
4. [Gestaltung](docs/04_design.md)
5. [Consent Mode und Signale](docs/05_signale.md)
6. [Conversions und Ereignisse](docs/10_ereignisse.md)
7. [Protokoll und Nachweis](docs/06_protokoll.md)
8. [API-Referenz](docs/07_api.md)
9. [Tipps und Tricks](docs/08_tipps.md)
10. [Umstieg vom consent_manager](docs/09_umstieg.md)

## Voraussetzungen

REDAXO ≥ 5.18, PHP ≥ 8.2. Optional: YRewrite liefert die Domains, das Cronjob-AddOn bereinigt das Protokoll, WriteAssist übersetzt Sprachfelder per Klick.

## Hinweis

Das AddOn ist ein Werkzeug und ersetzt keine Rechtsberatung. Vorlagen sind sorgfältig geprüft, Anbieter ändern Cookies und Laufzeiten aber ohne Ankündigung.

## Lizenz

MIT, siehe [LICENSE](LICENSE). Die optional ladbare [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database) steht unter Apache-2.0 und wird nicht mitgeliefert.
