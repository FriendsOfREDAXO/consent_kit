<?php

foreach (['group', 'service', 'item', 'variant', 'domain', 'revision', 'log', 'catalog'] as $table) {
    rex_sql_table::get(rex::getTable('consent_kit_' . $table))->drop();
}
