<?php

namespace KLXM\ConsentKit;

use rex;
use rex_sql;

final class Installer
{
    /** Gruppen in Anzeige-Reihenfolge; "necessary" ist nicht abwaehlbar. */
    public const GROUPS = [
        'necessary' => [
            'required' => 1,
            'name' => ['de' => 'Notwendig', 'en' => 'Necessary'],
            'description' => [
                'de' => 'Diese Dienste sind für den Betrieb der Website erforderlich und können nicht deaktiviert werden.',
                'en' => 'These services are required to operate the website and cannot be disabled.',
            ],
        ],
        'functional' => [
            'required' => 0,
            'name' => ['de' => 'Funktional', 'en' => 'Functional'],
            'description' => [
                'de' => 'Diese Dienste stellen Zusatzfunktionen bereit, etwa Spamschutz, Terminbuchung oder Chat.',
                'en' => 'These services provide additional features such as spam protection, appointment booking or chat.',
            ],
        ],
        'statistics' => [
            'required' => 0,
            'name' => ['de' => 'Statistik', 'en' => 'Statistics'],
            'description' => [
                'de' => 'Diese Dienste erfassen, wie die Website genutzt wird, um sie verbessern zu können.',
                'en' => 'These services record how the website is used so that it can be improved.',
            ],
        ],
        'marketing' => [
            'required' => 0,
            'name' => ['de' => 'Marketing', 'en' => 'Marketing'],
            'description' => [
                'de' => 'Diese Dienste werden eingesetzt, um Werbung auszuspielen und deren Erfolg zu messen.',
                'en' => 'These services are used to deliver advertising and measure its success.',
            ],
        ],
        'media' => [
            'required' => 0,
            'name' => ['de' => 'Externe Medien', 'en' => 'External media'],
            'description' => [
                'de' => 'Inhalte von Video-, Karten- und Social-Media-Plattformen werden erst nach Einwilligung geladen.',
                'en' => 'Content from video, map and social media platforms is only loaded after consent.',
            ],
        ],
    ];

    public static function seed(): void
    {
        $sql = rex_sql::factory();

        $sql->setQuery('SELECT COUNT(*) AS c FROM ' . rex::getTable('consent_kit_domain'));
        if (0 === (int) $sql->getValue('c')) {
            rex_sql::factory()
                ->setTable(rex::getTable('consent_kit_domain'))
                ->setValue('host', '*')
                ->insert();
        }

        $sql->setQuery('SELECT COUNT(*) AS c FROM ' . rex::getTable('consent_kit_group'));
        if ((int) $sql->getValue('c') > 0) {
            return;
        }

        $prio = 0;
        $necessaryId = 0;
        foreach (self::GROUPS as $key => $group) {
            $insert = rex_sql::factory()
                ->setTable(rex::getTable('consent_kit_group'))
                ->setValue('key', $key)
                ->setValue('prio', ++$prio)
                ->setValue('required', $group['required'])
                ->setValue('name', I18n::encode($group['name']))
                ->setValue('description', I18n::encode($group['description']));
            $insert->insert();
            if ('necessary' === $key) {
                $necessaryId = (int) $insert->getLastId();
            }
        }

        // Der eigene Consent-Cookie gehoert in jede Installation.
        $service = rex_sql::factory()
            ->setTable(rex::getTable('consent_kit_service'))
            ->setValue('key', 'consent_kit')
            ->setValue('group_id', $necessaryId)
            ->setValue('prio', 1)
            ->setValue('status', 1)
            ->setValue('name', 'Consent Kit')
            ->setValue('provider', '')
            ->setValue('description', I18n::encode([
                'de' => 'Speichert die hier getroffene Auswahl zu Diensten und Cookies.',
                'en' => 'Stores the selection of services and cookies made here.',
            ]))
            ->setValue('preset', 'consent_kit')
            ->addGlobalCreateFields('consent_kit')
            ->addGlobalUpdateFields('consent_kit');
        $service->insert();

        rex_sql::factory()
            ->setTable(rex::getTable('consent_kit_item'))
            ->setValue('service_id', (int) $service->getLastId())
            ->setValue('prio', 1)
            ->setValue('type', 'cookie')
            ->setValue('name', 'consent_kit')
            ->setValue('duration_value', 1)
            ->setValue('duration_unit', 'years')
            ->setValue('purpose', I18n::encode([
                'de' => 'Enthält die Einwilligungs-ID, den Stand der Konfiguration und die gewählten Dienste.',
                'en' => 'Contains the consent ID, the configuration revision and the selected services.',
            ]))
            ->insert();
        self::seedDismissItem((int) $service->getLastId());
    }

    /** Merkt sich fuer die Browser-Sitzung, dass der Hinweis ohne Entscheidung geschlossen wurde. */
    public static function seedDismissItem(int $serviceId): void
    {
        rex_sql::factory()
            ->setTable(rex::getTable('consent_kit_item'))
            ->setValue('service_id', $serviceId)
            ->setValue('prio', 2)
            ->setValue('type', 'session_storage')
            ->setValue('name', 'consent_kit_dismissed')
            ->setValue('duration_value', 0)
            ->setValue('duration_unit', 'session')
            ->setValue('purpose', I18n::encode([
                'de' => 'Merkt sich bis zum Schließen des Browsers, dass der Hinweis ohne Entscheidung geschlossen wurde.',
                'en' => 'Remembers until the browser is closed that the notice was closed without a decision.',
            ]))
            ->insert();
    }
}
