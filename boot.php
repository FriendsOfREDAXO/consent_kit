<?php

use KLXM\ConsentKit\Api\Lookup;
use KLXM\ConsentKit\Api\Save;
use KLXM\ConsentKit\Cache;
use KLXM\ConsentKit\Cronjob\LogPurge as LogPurgeCronjob;
use KLXM\ConsentKit\Frontend;

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
        $version = $addon->getVersion() . '-' . @filemtime($addon->getAssetsPath('backend.js'));
        rex_view::addCssFile($addon->getAssetsUrl('backend.css') . '?v=' . $version);
        rex_view::addJsFile($addon->getAssetsUrl('backend.js') . '?v=' . $version);
    }
}
