<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\CustomFields\Http\Requests\UpsertFieldValuesRequest;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Models\CustomFieldValue;
use Modules\CustomFields\Services\CustomFieldRegistry;

/**
 * Handles display and bulk upsert of custom field values per entity.
 *
 * index() renders a fallback read-only overview of all entities and their
 * custom field values. The primary editing flow happens inside entity modals
 * (injected via the hook system), so this page is rarely used directly.
 *
 * upsert() saves all field values for one entity in a single request.
 * It uses updateOrCreate() so the same endpoint handles both create and update.
 * All updateOrCreate() calls are wrapped in a DB::transaction() to ensure
 * that either all field values are saved or none are (atomicity).
 * Validation of the values map is delegated to UpsertFieldValuesRequest.
 * Activity logging for individual value changes is handled automatically
 * by the LogsActivity trait on CustomFieldValue.
 *
 * Entities are loaded via DB::table() (not via Eloquent models) to avoid
 * hard dependencies on optional modules that may not be installed.
 */
class CustomFieldValueController extends Controller
{
    /**
     * Renders the fallback overview page listing all entities with their custom field values.
     *
     * Returns 404 if the given object type is not installed or unknown.
     *
     * @param  string $objectType
     * @return View
     */
    public function index(string $objectType): View
    {
        abort_unless(CustomFieldRegistry::isValidObjectType($objectType), 404);

        $definitions = CustomFieldDefinition::where('object_type', $objectType)
            ->orderBy('sort_order')
            ->get();

        $entities = $this->loadEntities($objectType);

        // Build a nested map: values[entityId][definitionId] = value string
        $definitionIds = $definitions->pluck('id');
        $rawValues     = CustomFieldValue::whereIn('definition_id', $definitionIds)->get();

        $valuesByEntity = [];
        foreach ($rawValues as $v) {
            $valuesByEntity[$v->entity_id][$v->definition_id] = $v->value;
        }

        // JS data bridge for the view
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
     * Saves or updates all custom field values for a single entity.
     *
     * Iterates all definitions for the object type and calls updateOrCreate()
     * for each, so missing values are created and existing ones are updated.
     * Checkbox fields default to '0' when absent from the submitted form data.
     * All writes are wrapped in a single DB transaction for atomicity.
     *
     * Redirects back to the referring page (entity modal page) on success.
     *
     * @param  UpsertFieldValuesRequest $request
     * @param  string                   $objectType
     * @param  int                      $entityId
     * @return RedirectResponse
     */
    public function upsert(UpsertFieldValuesRequest $request, string $objectType, int $entityId): RedirectResponse
    {
        abort_unless(CustomFieldRegistry::isValidObjectType($objectType), 404);

        $definitions = CustomFieldDefinition::where('object_type', $objectType)->get();
        $submitted   = $request->validated()['values'] ?? [];

        DB::transaction(function () use ($definitions, $submitted, $entityId): void {
            foreach ($definitions as $def) {
                if ($def->field_type === 'checkbox') {
                    // Unchecked checkboxes are not submitted – treat absence as false ('0')
                    $value = ($submitted[$def->id] ?? '0') === '1' ? '1' : '0';
                } else {
                    $value = isset($submitted[$def->id]) && $submitted[$def->id] !== ''
                        ? (string) $submitted[$def->id]
                        : null;
                }

                CustomFieldValue::updateOrCreate(
                    ['definition_id' => $def->id, 'entity_id' => $entityId],
                    ['value'         => $value]
                );
            }
        });

        return redirect()->back()->with('success', 'Eigene Felder gespeichert.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Loads the entity list for a given object type via DB facade.
     *
     * Uses raw DB queries to avoid importing Eloquent models from optional modules
     * that might not be installed. Returns an empty collection for unknown types.
     *
     * @param  string $objectType
     * @return Collection
     */
    private function loadEntities(string $objectType): Collection
    {
        return match ($objectType) {
            'member' => DB::table('members')
                ->select('id', DB::raw("CONCAT(last_name, ', ', first_name) AS _label"))
                ->whereNull('deleted_at')
                ->orderBy('last_name')
                ->get(),

            'team' => DB::table('teams')
                ->select('id', DB::raw('name AS _label'))
                ->orderBy('name')
                ->get(),

            'event' => DB::table('events')
                ->select('id', DB::raw('title AS _label'))
                ->orderBy('starts_at')
                ->get(),

            'management_function' => DB::table('management_functions')
                ->select('id', DB::raw('name AS _label'))
                ->orderBy('name')
                ->get(),

            'management_task' => DB::table('management_tasks')
                ->select('id', DB::raw('name AS _label'))
                ->orderBy('name')
                ->get(),

            default => collect(),
        };
    }
}
