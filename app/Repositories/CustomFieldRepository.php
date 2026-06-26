<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CustomFieldRepository
 *
 * Zentraler Ladeort für Feld-Definitionen und -Werte des CustomFields-Moduls.
 * Dieses Repository kapselt die rohen DB-Abfragen, damit die Modul-Controller
 * (Members, Teams, Events, Management) nicht alle dasselbe 30-Zeilen-Fragment
 * wiederholen müssen.
 *
 * Graceful: Existiert die Tabelle `custom_field_definitions` nicht
 * (CustomFields-Modul nicht installiert), werden leere Arrays zurückgegeben.
 *
 * @used-by \Modules\Members\Http\Controllers\MemberController
 * @used-by \Modules\Teams\Http\Controllers\TeamController
 * @used-by \Modules\Events\Http\Controllers\EventController
 * @used-by \Modules\Management\Http\Controllers\ManagementController
 */
class CustomFieldRepository
{
    /**
     * Lädt Feld-Definitionen und gespeicherte Werte für einen Objekt-Typ
     * in zwei DB-Abfragen.
     *
     * @param  string  $objectType  z.B. 'member', 'team', 'event'
     * @return array{defs: list<array>, values: array<int, array<int, string>>}
     *
     * Rückgabe:
     *   'defs'   → [ ['id', 'label', 'field_type', 'options', 'placeholder', 'is_required'], ... ]
     *   'values' → [ entityId => [ fieldId => value ], ... ]
     */
    public function loadForObjectType(string $objectType): array
    {
        $defs   = [];
        $values = [];

        if (!Schema::hasTable('custom_field_definitions')) {
            return compact('defs', 'values');
        }

        $rawDefs = DB::table('custom_field_definitions')
            ->where('object_type', $objectType)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        foreach ($rawDefs as $d) {
            $defs[] = $this->normalizeDef($d);
        }

        if (!empty($defs)) {
            $defIds = array_column($defs, 'id');
            $vals   = DB::table('custom_field_values')
                ->whereIn('field_id', $defIds)
                ->get();
            foreach ($vals as $v) {
                $values[$v->entity_id][$v->field_id] = $v->value;
            }
        }

        return compact('defs', 'values');
    }

    /**
     * Lädt Feld-Definitionen und gespeicherte Werte für mehrere Objekt-Typen
     * in NUR ZWEI DB-Abfragen (unabhängig von der Anzahl der Typen).
     *
     * Geeignet für Controller, die mehrere Typen gleichzeitig brauchen
     * (z.B. ManagementController: management_function + management_task).
     *
     * @param  string[]  $objectTypes  z.B. ['management_function', 'management_task']
     * @return array<string, array{defs: list<array>, values: array<int, array<int, string>>}>
     *
     * Rückgabe:
     *   [ 'management_function' => ['defs' => [...], 'values' => [...]], ... ]
     */
    public function loadForObjectTypes(array $objectTypes): array
    {
        // Ergebnis-Struktur mit leeren Arrays initialisieren
        $result = [];
        foreach ($objectTypes as $type) {
            $result[$type] = ['defs' => [], 'values' => []];
        }

        if (!Schema::hasTable('custom_field_definitions') || empty($objectTypes)) {
            return $result;
        }

        // Alle Definitionen auf einmal laden
        $rawDefs = DB::table('custom_field_definitions')
            ->whereIn('object_type', $objectTypes)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        // defId → objectType-Mapping für spätere Wert-Zuordnung
        $defIdToType = [];

        foreach ($rawDefs as $d) {
            $result[$d->object_type]['defs'][] = $this->normalizeDef($d);
            $defIdToType[$d->id]               = $d->object_type;
        }

        // Alle Werte auf einmal laden
        if (!empty($defIdToType)) {
            $vals = DB::table('custom_field_values')
                ->whereIn('field_id', array_keys($defIdToType))
                ->get();

            foreach ($vals as $v) {
                $type = $defIdToType[$v->field_id] ?? null;
                if ($type === null) {
                    continue;
                }
                $result[$type]['values'][$v->entity_id][$v->field_id] = $v->value;
            }
        }

        return $result;
    }

    /**
     * Normalisiert einen rohen DB-Datensatz in das einheitliche Definitions-Format.
     */
    private function normalizeDef(object $d): array
    {
        return [
            'id'          => $d->id,
            'label'       => $d->label,
            'field_type'  => $d->field_type,
            'options'     => $d->options ? json_decode($d->options, true) : [],
            'placeholder' => $d->placeholder ?? '',
            'is_required' => (bool) $d->is_required,
        ];
    }
}
