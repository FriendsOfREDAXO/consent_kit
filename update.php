<?php

/*
 * Update ueber den Installer. Laeuft aus dem neuen Ordner, bevor er den alten ersetzt.
 * install.php ergaenzt fehlende Tabellen und Spalten und fuellt Grunddaten nur in leere Tabellen.
 */

// 1.6.0: Spalte embed_only („nur Einbettung mit Platzhalter“) – vorher merken, ob es sie schon gibt.
$serviceTable = rex::getTable('consent_kit_service');
$hadEmbedOnly = rex_sql_table::get($serviceTable)->hasColumn('embed_only');

include __DIR__ . '/install.php';

/*
 * Neu angelegte Spalte: Dienste aus Vorlagen uebernehmen den Wert der Vorlage (z. B. YouTube, Vimeo,
 * Karten), eigene Dienste bleiben aus. Ob der Hinweis dann wirklich entfaellt, entscheidet EmbedOnly::issues()
 * am fertigen Dienst. lib/ des neuen Ordners ist noch nicht im Autoloader.
 */
if (!$hadEmbedOnly) {
    if (!class_exists(FriendsOfRedaxo\ConsentKit\EmbedOnly::class)) {
        require_once __DIR__ . '/lib/EmbedOnly.php';
    }
    foreach (rex_sql::factory()->getArray('SELECT id, preset FROM ' . $serviceTable . " WHERE preset <> ''") as $row) {
        $preset = FriendsOfRedaxo\ConsentKit\PresetRepository::get((string) $row['preset']);
        if (null !== $preset && FriendsOfRedaxo\ConsentKit\EmbedOnly::fromPreset($preset)) {
            rex_sql::factory()->setQuery('UPDATE ' . $serviceTable . ' SET embed_only = 1 WHERE id = ?', [(int) $row['id']]);
        }
    }
}

// Die gecachte Frontend-Konfiguration stammt aus der alten Version (etwa ohne neue Texte).
$cache = rex_path::addonCache('consent_kit');
if (is_dir($cache)) {
    rex_dir::delete($cache, false);
}
