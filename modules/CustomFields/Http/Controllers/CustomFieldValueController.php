<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;
use Modules\CustomFields\Services\CustomFieldRegistry;

class CustomFieldValueController extends Controller
{
    /**
     * Zeigt alle Entitäten eines Objekt-Typs mit ihren Custom-Field-Werten.
     * (Fallback-Seite – primäre Bearbeitung erfolgt in den Entity-Modals)
     */
    public function index(string $objectType): View
    {
        abort_unless(CustomFieldRegistry::isValidObjectType($objectType), 404);

        $definitions = CustomFieldDefinition::where('object_type', $objectType)
            ->orderBy('sort_order')
            ->get();

        // Entitäten des Objekt-Typs laden (ohne Modul-Import – via DB-Facade)
        $entities = $this->loadEntities($objectType);

        // Alle Werte für diesen Objekt-Typ laden
        // values[entityId][fieldId] = value
        $fieldIds = $definitions->pluck('id');
        $rawValues = CustomFieldValue::whereIn('field_id', $fieldIds)->get();

        $valuesByEntity = [];
        foreach ($rawValues as $v) {
            $valuesByEntity[$v->entity_id][$v->field_id] = $v->value;
        }

        // Data Bridge für JS
        $valuesJs = [];
        foreach ($entities as $entity) {
            $valuesJs[$entity->id] = $valuesByEntity[$entity->id] ?? [];
        }

        $objectTypeLabel = CustomFieldRegistry::objectTypeLabel($objectType);

        return view('custom-fields::values', compact(
            'objectType', 'objectTypeLabel', 'definitions', 'entities', 'valuesByEntity', 'valuesJs'
        ));
    }

    /**
     * Speichert / aktualisiert alle Feldwerte für eine konkrete Entität.
     * Leitet zurück auf die aufrufende Seite (z.B. Mitglieder- oder Teams-Seite).
     */
    public function upsert(Request $request, string $objectType, int $entityId): RedirectResponse
    {
        abort_unless(CustomFieldRegistry::isValidObjectType($objectType), 404);

        $definitions = CustomFieldDefinition::where('object_type', $objectType)->get();
        $submitted   = $request->input('values', []);

        foreach ($definitions as $def) {
            if ($def->field_type === 'checkbox') {
                // Checkbox: nicht übertragen = false; Wert direkt aus dem Formular lesen
                $value = ($submitted[$def->id] ?? '0') === '1' ? '1' : '0';
            } else {
                $value = isset($submitted[$def->id]) && $submitted[$def->id] !== ''
                    ? (string) $submitted[$def->id]
                    : null;
            }

            CustomFieldValue::updateOrCreate(
                ['field_id' => $def->id, 'entity_id' => $entityId],
                ['value'    => $value]
            );
        }

        // Zurück zur aufrufenden Seite (Entity-Modal-Seite) statt zur CF-Verwaltung
        return redirect()->back()->with('success', 'Eigene Felder gespeichert.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Lädt die Entitäten für einen Objekt-Typ via DB-Facade.
     * Kein direkter Modell-Import (optionale Module könnten nicht installiert sein).
     */
    private function loadEntities(string $objectType): \Illuminate\Support\Collection
    {
        switch ($objectType) {
            case 'member':
                return DB::table('members')
                    ->select('id', DB::raw("CONCAT(last_name, ', ', first_name) AS _label"))
                    ->whereNull('deleted_at')
                    ->orderBy('last_name')
                    ->get();

            case 'team':
                return DB::table('teams')
                    ->select('id', DB::raw('name AS _label'))
                    ->orderBy('name')
                    ->get();

            case 'event':
                return DB::table('events')
                    ->select('id', DB::raw('title AS _label'))
                    ->orderBy('starts_at')
                    ->get();

            case 'management_function':
                return DB::table('management_functions')
                    ->select('id', DB::raw('name AS _label'))
                    ->orderBy('name')
                    ->get();

            case 'management_task':
                return DB::table('management_tasks')
                    ->select('id', DB::raw('name AS _label'))
                    ->orderBy('name')
                    ->get();

            default:
                return collect();
        }
    }
}
