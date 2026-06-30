<?php

declare(strict_types=1);

namespace Modules\CustomFields\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Provides runtime configuration for the CustomFields module.
 *
 * Determines which object types are available for custom field definitions
 * based on which module tables currently exist in the database.
 * This allows optional modules (Teams, Events, Management) to be absent
 * without breaking the CustomFields module.
 *
 * Note: Schema::hasTable() is used intentionally here (instead of class_exists())
 * because the registry must work at database level without importing Eloquent models
 * from optional modules that may not be installed.
 */
class CustomFieldRegistry
{
    /**
     * Returns all object types that currently support custom fields.
     *
     * Keys are the object_type values stored in the DB.
     * Values are the German display labels used in the admin UI.
     * The 'member' type is always available; others depend on installed modules.
     *
     * @return array<string, string>
     */
    public static function availableObjectTypes(): array
    {
        $types = [
            'member' => 'Mitglied',
        ];

        if (Schema::hasTable('teams')) {
            $types['team'] = 'Team';
        }
        if (Schema::hasTable('events')) {
            $types['event'] = 'Termin';
        }
        if (Schema::hasTable('management_functions')) {
            $types['management_function'] = 'Funktion';
        }
        if (Schema::hasTable('management_tasks')) {
            $types['management_task'] = 'Aufgabe';
        }

        return $types;
    }

    /**
     * Returns all supported field types with their German display labels.
     *
     * @return array<string, string>
     */
    public static function fieldTypes(): array
    {
        return [
            'text'     => 'Text (einzeilig)',
            'textarea' => 'Text (mehrzeilig)',
            'number'   => 'Nummer (Ganzzahl)',
            'decimal'  => 'Nummer (Dezimalzahl)',
            'select'   => 'Auswahl (Dropdown)',
            'checkbox' => 'Ja / Nein',
            'date'     => 'Datum',
            'email'    => 'E-Mail-Adresse',
            'phone'    => 'Telefon',
            'url'      => 'Website / URL',
            'whatsapp' => 'WhatsApp',
        ];
    }

    /**
     * Returns field types that render as a plain HTML input element.
     *
     * Excludes 'select' (renders a <select>), 'checkbox' (renders a checkbox),
     * and 'textarea' (renders a <textarea>).
     *
     * @return list<string>
     */
    public static function inputFieldTypes(): array
    {
        return ['text', 'number', 'decimal', 'email', 'phone', 'url', 'whatsapp', 'date'];
    }

    /**
     * Returns the German display label for an object type, or the key itself if unknown.
     *
     * @param  string $objectType
     * @return string
     */
    public static function objectTypeLabel(string $objectType): string
    {
        return static::availableObjectTypes()[$objectType] ?? $objectType;
    }

    /**
     * Returns whether the given object type is installed and supported.
     *
     * @param  string $objectType
     * @return bool
     */
    public static function isValidObjectType(string $objectType): bool
    {
        return array_key_exists($objectType, static::availableObjectTypes());
    }
}
