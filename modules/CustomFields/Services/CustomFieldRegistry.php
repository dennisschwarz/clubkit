<?php

declare(strict_types=1);

namespace Modules\CustomFields\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Registry für Custom-Fields-Konfiguration.
 *
 * Stellt zur Laufzeit fest, für welche Objekt-Typen Custom Fields angelegt
 * werden können – abhängig von den installierten Modulen.
 */
class CustomFieldRegistry
{
    /**
     * Gibt alle verfügbaren Objekt-Typen zurück.
     * Schlüssel = object_type in der DB, Wert = Anzeigename.
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
     * Alle unterstützten Feldtypen.
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
     * Feldtypen, die ein freies Texteingabefeld im HTML erzeugen
     * (d.h. kein select, checkbox, textarea).
     */
    public static function inputFieldTypes(): array
    {
        return ['text', 'number', 'decimal', 'email', 'phone', 'url', 'whatsapp', 'date'];
    }

    /**
     * Gibt den Anzeigenamen eines Objekt-Typs zurück.
     */
    public static function objectTypeLabel(string $objectType): string
    {
        return static::availableObjectTypes()[$objectType] ?? $objectType;
    }

    /**
     * Prüft ob ein Objekt-Typ installiert und erlaubt ist.
     */
    public static function isValidObjectType(string $objectType): bool
    {
        return array_key_exists($objectType, static::availableObjectTypes());
    }
}
