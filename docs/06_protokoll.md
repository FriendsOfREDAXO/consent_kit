# Protokoll und Nachweis

Wer sich auf eine Einwilligung beruft, muss sie nachweisen können (Art. 7 Abs. 1 DSGVO). Das Protokoll liefert diesen Nachweis und bleibt dabei datensparsam.

## Was gespeichert wird

| Feld | Inhalt |
| --- | --- |
| Einwilligungs-ID | zufällige UUID, steht auch im Cookie des Besuchers |
| Zeitpunkt | Serverzeit |
| Domain | Domain-Eintrag, unter dem entschieden wurde |
| Stand | Verweis auf den Schnappschuss der Konfiguration |
| Entscheidung | Alle akzeptiert · Alle abgelehnt · Auswahl · Abgelehnt per GPC · Über Platzhalter erlaubt |
| Dienste | akzeptierte und abgelehnte Schlüssel |
| Signal | ob der Browser GPC gesendet hat |
| Sprache | Sprache des Hinweises |

**Nicht** gespeichert werden IP-Adresse, User-Agent und die aufgerufene Seite.

## Der Stand

Jede einwilligungsrelevante Änderung (Dienst neu, entfernt, anderer Anbieter, andere Cookies) erzeugt einen neuen **Stand**. Ein Klick auf `#12` im Protokoll zeigt, was damals zur Auswahl stand: Gruppen, Dienste, Anbieter, Cookies mit Laufzeiten. Damit lässt sich belegen, *worin* jemand eingewilligt hat, auch wenn die Konfiguration längst anders aussieht.

## Auskunft und Nachweis im Einzelfall

Besucher finden ihre Einwilligungs-ID unten im Einstellungen-Dialog („Einwilligungs-ID: … · gespeichert am …“). Mit dieser ID lässt sich das Protokoll filtern; alle Entscheidungen derselben Person auf diesem Gerät tragen dieselbe ID.

## Auswertung

Oben stehen die Entscheidungen der letzten 30 Tage. Je Einwilligungs-ID zählt nur die letzte Entscheidung, damit Mehrfachklicks die Quote nicht verzerren. „Zustimmung je Dienst“ zeigt, wie viele Besucher den jeweiligen Dienst zuletzt akzeptiert haben.

Filter (ID, Entscheidung, Zeitraum) gelten auch für den **CSV-Export** (Semikolon-getrennt, UTF-8 mit BOM, öffnet direkt in Excel).

## Aufbewahrung

Standard sind 1095 Tage – die regelmäßige Verjährungsfrist, innerhalb der ein Nachweis gebraucht werden könnte. Kürzer ist möglich, `0` löscht nie. Gelöscht wird

- per Cronjob **„Consent Kit: Protokoll bereinigen“** (Cronjob-AddOn, z. B. täglich),
- per Konsole `php redaxo/bin/console consent_kit:log-purge` (`--days=90` überschreibt die Einstellung),
- von Hand über **Protokoll → Jetzt bereinigen** (Admin).

## Der Cookie

Name `consent_kit`, `SameSite=Lax`, `Secure` unter HTTPS, Pfad `/`, Laufzeit wie eingestellt. Er wird sofort per JavaScript gesetzt und zusätzlich vom Server per HTTP-Header bestätigt – Safari kürzt rein per JavaScript gesetzte Cookies sonst auf sieben Tage. Der Aufbau steht in der [API-Referenz](07_api.md#cookie).

Der Cookie gilt pro Host. `example.org` und `shop.example.org` fragen getrennt.
