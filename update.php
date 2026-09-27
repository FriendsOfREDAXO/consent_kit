<?php

/*
 * Update ueber den Installer. Laeuft aus dem neuen Ordner, bevor er den alten ersetzt.
 * install.php ergaenzt fehlende Tabellen und Spalten und fuellt Grunddaten nur in leere Tabellen.
 */
include __DIR__ . '/install.php';

// Die gecachte Frontend-Konfiguration stammt aus der alten Version (etwa ohne neue Texte).
$cache = rex_path::addonCache('consent_kit');
if (is_dir($cache)) {
    rex_dir::delete($cache, false);
}
