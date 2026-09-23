# Einrichtung

## Installation

Nach der Installation gibt es fünf Gruppen (Notwendig, Funktional, Statistik, Marketing, Externe Medien) und einen notwendigen Dienst „Consent Kit“, der den eigenen Cookie dokumentiert. Der Hinweis ist eingebunden, erscheint aber erst, wenn ein einwilligungspflichtiger Dienst aktiv ist.

Die Seite **Dienste** zeigt oben einen Status: Ist der Hinweis eingebunden? Sind Datenschutzerklärung und Impressum verlinkt? Gibt es unvollständige Dienste?

## Rechtstexte und Domains

Unter **Einstellungen → Rechtstexte und Domains** werden Datenschutzerklärung und Impressum als Artikel gewählt. Beide erscheinen als Links im Hinweis und müssen ohne Entscheidung lesbar sein. Mit Schließen-Schaltfläche (×) ist das überall gegeben. Ist das × abgeschaltet, erscheint ein „Dialog“ auf genau diesen beiden Seiten stattdessen als nicht blockierende Box.

- **Alle Domains** gilt überall, wo keine eigene Domain passt.
- Domains aus **YRewrite** werden automatisch angelegt. Ohne eigene Rechtstexte gelten die von „Alle Domains“.
- Weitere Domains lassen sich von Hand ergänzen. `https://`, `www.`, Port und Pfad werden beim Speichern entfernt; `www.example.org` und `example.org` sind dieselbe Domain.

## Darstellung

| Einstellung | Bedeutung |
| --- | --- |
| Automatisch einbinden | Fügt alles Nötige per `OUTPUT_FILTER` in den `<head>` ein. Alternative: [`REX_CONSENT_KIT[]` oder `Consent::head()`](03_einbindung.md#manuelle-einbindung). |
| Form | **Box** (Ecke, Seite bleibt bedienbar), **Leiste** (volle Breite), **Dialog** (mittig, Seite gesperrt), **Off-Canvas** (Panel über die volle Höhe am linken oder rechten Rand, Seite bleibt bedienbar). |
| Position | Ecke der Box, oben/unten bei der Leiste, links/rechts beim Off-Canvas-Panel. |
| Gruppen im Hinweis zeigen | Nur bei **Dialog** und **Off-Canvas**: Der Hinweis listet die Gruppen mit Schaltern, die mittlere Schaltfläche wird „Auswahl speichern“. Es ist nichts vorausgewählt – eine Vorbelegung wäre keine wirksame Einwilligung. Die einzelnen Dienste bleiben in den Einstellungen. Standard: aus. |
| Farbschema | Hell, Dunkel oder Automatisch (folgt dem System des Besuchers – nur sinnvoll, wenn die Website selbst einen Dark Mode hat). |
| Schließen-Schaltfläche (×) | Schließt den Hinweis **ohne Entscheidung**: Es wird nichts geladen, und der Hinweis bleibt bis zum Ende der Browser-Sitzung ausgeblendet (Escape wirkt genauso). Ohne × muss im Dialog entschieden werden. Standard: an. |
| Schwebende Schaltfläche | Öffnet die Einstellungen erneut. Ohne sie muss ein [eigener Link](03_einbindung.md#einstellungen-erneut-oeffnen) vorhanden sein: Der Widerruf muss so einfach sein wie die Einwilligung. |

## Einwilligung und Protokoll

| Einstellung | Standard | Bedeutung |
| --- | --- | --- |
| Gültigkeit der Entscheidung | 365 Tage | Danach wird erneut gefragt, auch nach einer Ablehnung. Browser begrenzen Cookies auf rund 400 Tage. |
| Seite nach Widerruf neu laden | an | Bereits geladene Scripts lassen sich nur so sicher stoppen. |
| Einbettungen aus CKEditor 5 und TinyMCE sperren | an | Wandelt `<oembed>`-Tags der Editoren in [2-Klick-Platzhalter](03_einbindung.md#inhalte-aus-dem-editor) um. |
| iframes automatisch sperren | aus | Ersetzt iframes bekannter Hosts durch den [2-Klick-Platzhalter](03_einbindung.md#externe-inhalte). |
| Aufbewahrung des Protokolls | 1095 Tage | `0` = nie löschen. Siehe [Protokoll](06_protokoll.md). |

## Rechte

| Recht | Umfang |
| --- | --- |
| `consent_kit[]` | Dienste und Texte |
| `consent_kit[settings]` | Einstellungen und Design |
| `consent_kit[log]` | Protokoll |
| Admin | Werkzeuge (Scanner, Katalog, Übernahme), Protokoll bereinigen |

## Texte

Unter **Texte** lässt sich jeder Text pro Sprache überschreiben. Leere Felder verwenden den Standard (grau sichtbar). Mitgeliefert sind Deutsch und Englisch; andere Sprachen zeigen Englisch, bis eigene Texte eingetragen sind. Platzhalter: `{name}` (Dienst/Gruppe), `{n}` (Anzahl), `{id}` und `{date}` (Einwilligungs-ID und Datum).

### Übersetzen mit WriteAssist

Ist das AddOn [WriteAssist](https://github.com/FriendsOfREDAXO/writeassist) installiert und dort DeepL oder eine Text-KI konfiguriert, steht an jedem Sprachfeld eine Schaltfläche „Aus DE übersetzen“ – bei Beschreibungen von Diensten und Gruppen, beim Zweck von Cookies und auf der Seite **Texte**. Sie füllt das Feld aus der Standardsprache; vorhandener Text wird nur nach Rückfrage ersetzt. Ohne WriteAssist fehlt die Schaltfläche einfach.

Für mehr auf einmal:

- **Alle leeren Sprachfelder übersetzen** – unten in jedem Dienst-, Gruppen- und Texte-Formular. Füllt nacheinander alle leeren Felder des Formulars und zeigt den Fortschritt; anschließend speichern.
- **Werkzeuge → Fehlende Übersetzungen ergänzen** – für eine neu angelegte Sprache. Übersetzt serverseitig alle Gruppen, Dienste, Cookie-Zwecke und Frontend-Texte, die in der Zielsprache noch leer sind, und speichert sie direkt. Vorhandene Übersetzungen bleiben unangetastet. Je nach Umfang und Dienst dauert das einige Minuten (etwa 90 Felder ≈ 5 Minuten mit einer Text-KI).

Maschinelle Übersetzungen sind ein Entwurf – die Ergebnisse bitte stichprobenartig prüfen, besonders bei Text-KI-Anbietern, die gelegentlich Steuerwörter mitübersetzen.

Texte zählen nicht als Änderung der Einwilligungsgrundlage – Besucher werden deshalb nicht erneut gefragt.
