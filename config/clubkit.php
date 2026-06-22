<?php

declare(strict_types=1);

/**
 * ClubKit Konfiguration
 * Datei: config/clubkit.php
 *
 * Alle ClubKit-spezifischen Einstellungen.
 * Werte werden vom Installer in .env geschrieben und hier ausgelesen.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Installierte Module
    |--------------------------------------------------------------------------
    | Komma-separierte Liste der aktiven Module, gesetzt vom Installer.
    | Beispiel: "core,teams,fixtures,training"
    */
    'modules' => env('CLUBKIT_MODULES', 'core'),

    /*
    |--------------------------------------------------------------------------
    | Jugendmodus
    |--------------------------------------------------------------------------
    | Aktiviert Erziehungsberechtigte und Jugend-spezifische Funktionen.
    */
    'youth_club' => (bool) env('CLUBKIT_YOUTH_CLUB', false),

    /*
    |--------------------------------------------------------------------------
    | Verfügbare Module (Referenz)
    |--------------------------------------------------------------------------
    */
    'available_modules' => [
        'core'      => 'Kern (Auth, Settings, Nutzer)',
        'teams'     => 'Teams & Mitglieder',
        'fixtures'  => 'Spieltage & Aufgaben',
        'training'  => 'Training',
        'guardians' => 'Erziehungsberechtigte',
        'finances'  => 'Finanzen',
        'events'    => 'Veranstaltungen & Elternabend',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollen
    |--------------------------------------------------------------------------
    | Standard-Rollen die beim ersten Start angelegt werden.
    */
    'roles' => [
        'admin'   => 'Administrator',
        'trainer' => 'Trainer',
        'member'  => 'Mitglied',
        'parent'  => 'Erziehungsberechtigte/r',
    ],

];
