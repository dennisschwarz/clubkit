<?php

declare(strict_types=1);

/**
 * ClubKit application configuration.
 *
 * All ClubKit-specific settings are defined here.
 * Values are written by the installer to .env and read from there.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Installed Modules
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of active modules, set by the installer.
    | Example: "core,teams,events,management"
    |
    */
    'modules' => env('CLUBKIT_MODULES', 'core'),

    /*
    |--------------------------------------------------------------------------
    | Youth Club Mode
    |--------------------------------------------------------------------------
    |
    | Enables guardian / legal-representative functionality and
    | youth-specific features when set to true.
    |
    */
    'youth_club' => (bool) env('CLUBKIT_YOUTH_CLUB', false),

    /*
    |--------------------------------------------------------------------------
    | Available Modules (Reference)
    |--------------------------------------------------------------------------
    |
    | Human-readable labels for each module slug.
    | Used by the installer UI and the admin module management panel.
    |
    */
    'available_modules' => [
        'core'            => 'Core (Auth, Settings, Users)',
        'members'         => 'Members',
        'teams'           => 'Teams',
        'events'          => 'Events',
        'management'      => 'Management Functions & Tasks',
        'import'          => 'Member Import',
        'custom-fields'   => 'Custom Fields',
        'treasury'        => 'Treasury',
        'youth-club-mode' => 'Youth Club Mode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    |
    | Roles seeded on first startup by RoleSeeder.
    |
    */
    'roles' => [
        'super-admin' => 'Super Administrator',
        'admin'       => 'Administrator',
        'trainer'     => 'Trainer',
        'member'      => 'Member',
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Admin Credentials
    |--------------------------------------------------------------------------
    |
    | Used by DatabaseSeeder to create the first admin user.
    | Set via .env before running db:seed for the first time.
    | Defaults are safe for a fresh local installation only.
    |
    */
    'admin' => [
        'email'    => env('ADMIN_EMAIL',    'admin@local.dev'),
        'name'     => env('ADMIN_NAME',     'Admin'),
        'password' => env('ADMIN_PASSWORD', 'admin123'),
    ],

];
