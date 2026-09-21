<?php

use KLXM\ConsentKit\Installer;

rex_sql_table::get(rex::getTable('consent_kit_group'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('key', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('prio', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('required', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('name', 'text'))
    ->ensureColumn(new rex_sql_column('description', 'text'))
    ->ensureIndex(new rex_sql_index('key', ['key'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_service'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('key', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('group_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('prio', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)', false, '1'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('provider', 'text'))
    ->ensureColumn(new rex_sql_column('privacy_url', 'varchar(500)', false, ''))
    ->ensureColumn(new rex_sql_column('description', 'text'))
    ->ensureColumn(new rex_sql_column('params', 'text'))
    ->ensureColumn(new rex_sql_column('html_head', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('html_body', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('js_default', 'text'))
    ->ensureColumn(new rex_sql_column('js_accept', 'text'))
    ->ensureColumn(new rex_sql_column('js_revoke', 'text'))
    ->ensureColumn(new rex_sql_column('gcm_signals', 'varchar(255)', false, ''))
    ->ensureColumn(new rex_sql_column('embed_hosts', 'text'))
    ->ensureColumn(new rex_sql_column('domain_ids', 'varchar(255)', false, ''))
    ->ensureColumn(new rex_sql_column('preset', 'varchar(64)', false, ''))
    ->ensureGlobalColumns()
    ->ensureIndex(new rex_sql_index('key', ['key'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_item'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('service_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('prio', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('type', 'varchar(20)', false, 'cookie'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('duration_value', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('duration_unit', 'varchar(20)', false, 'session'))
    ->ensureColumn(new rex_sql_column('purpose', 'text'))
    ->ensureIndex(new rex_sql_index('service_id', ['service_id']))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_variant'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('service_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('domain_id', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('clang', 'varchar(10)', false, ''))
    ->ensureColumn(new rex_sql_column('params', 'text'))
    ->ensureColumn(new rex_sql_column('html_head', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('html_body', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('js_default', 'text'))
    ->ensureColumn(new rex_sql_column('js_accept', 'text'))
    ->ensureColumn(new rex_sql_column('js_revoke', 'text'))
    ->ensureIndex(new rex_sql_index('service_id', ['service_id']))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_domain'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('privacy_article_id', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('imprint_article_id', 'int(10) unsigned', false, '0'))
    ->ensureIndex(new rex_sql_index('host', ['host'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_revision'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('domain_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('hash', 'char(40)'))
    ->ensureColumn(new rex_sql_column('snapshot', 'mediumtext'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureIndex(new rex_sql_index('domain_hash', ['domain_id', 'hash']))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_log'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('consent_id', 'char(36)'))
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('revision_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('action', 'varchar(20)'))
    ->ensureColumn(new rex_sql_column('accepted', 'text'))
    ->ensureColumn(new rex_sql_column('rejected', 'text'))
    ->ensureColumn(new rex_sql_column('gpc', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('clang', 'varchar(10)', false, ''))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->ensureIndex(new rex_sql_index('consent_id', ['consent_id']))
    ->ensureIndex(new rex_sql_index('createdate', ['createdate']))
    ->ensure();

rex_sql_table::get(rex::getTable('consent_kit_catalog'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('ocd_id', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('platform', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('category', 'varchar(64)', false, ''))
    ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('host', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('description', 'text'))
    ->ensureColumn(new rex_sql_column('retention', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('controller', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('wildcard', 'tinyint(1)', false, '0'))
    ->ensureIndex(new rex_sql_index('name', ['name']))
    ->ensure();

// lib/ ist waehrend der Installation noch nicht im Autoloader.
require_once __DIR__ . '/lib/I18n.php';
require_once __DIR__ . '/lib/Installer.php';
Installer::seed();
