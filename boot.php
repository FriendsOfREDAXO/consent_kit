<?php

use FriendsOfRedaxo\ConsentKit\Api\Lookup;
use FriendsOfRedaxo\ConsentKit\Api\Save;
use FriendsOfRedaxo\ConsentKit\Cache;
use FriendsOfRedaxo\ConsentKit\Cronjob\LogPurge as LogPurgeCronjob;
use FriendsOfRedaxo\ConsentKit\Frontend;

rex_perm::register('consent_kit[]');
rex_perm::register('consent_kit[settings]');
rex_perm::register('consent_kit[log]');

// Namespaced rex_api_function-Klassen muessen explizit registriert werden.
rex_api_function::register('consent_kit', Save::class);
rex_api_function::register('consent_kit_lookup', Lookup::class);

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(LogPurgeCronjob::class);
}

if (rex::isFrontend()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        $ep->setSubject(Frontend::inject((string) $ep->getSubject()));
    }, rex_extension::LATE);
}

if (rex::isBackend() && rex::getUser()) {
    // Links im Hinweis haengen an Artikeln und Sprachen.
    foreach (['CLANG_ADDED', 'CLANG_UPDATED', 'CLANG_DELETED'] as $extensionPoint) {
        rex_extension::register($extensionPoint, static function () {
            Cache::clear();
        });
    }

    if ('consent_kit' === rex_be_controller::getCurrentPagePart(1)) {
        $addon = rex_addon::get('consent_kit');
        $file = $addon->getAssetsPath('backend.js');
        $version = $addon->getVersion() . '-' . (is_file($file) ? filemtime($file) : 0);
        rex_view::addCssFile($addon->getAssetsUrl('backend.css') . '?v=' . $version);
        rex_view::addJsFile($addon->getAssetsUrl('backend.js') . '?v=' . $version);
    }
}
