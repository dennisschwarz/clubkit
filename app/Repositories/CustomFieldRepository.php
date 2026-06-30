<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central loading point for CustomFields module field definitions and values.
 *
 * Encapsulates raw DB queries so that module controllers (Members, Teams, Events,
 * Management) do not each repeat the same query fragment.
 *
 * Graceful: when the custom_field_definitions table does not exist
 * (CustomFields module not installed), empty arrays are returned without error.
 *
 * @see \Modules\Members\Http\Controllers\MemberController
 * @see \Modules\Teams\Http\Controllers\TeamController
 * @see \Modules\Events\Http\Controllers\EventController
 * @see \Modules\Management\Http\Controllers\ManagementController
 */
class CustomFieldRepository
{
    /**
     * Loads field definitions and stored values for a single object type
     * in exactly two database queries.
     *
     * @param  string $objectType  e.g. 'member', 'team', 'event'
     * @return array{defs: list<array<string, mixed>>, values: array<int, array<int, string>>}
     *         defs   → [['id', 'label', 'field_type', 'options', 'placeholder', 'is_required'], ...]
     *         values → [entityId => [definitionId => value], ...]
     */
    public function loadForObjectType(string $objectType): array
    {
        $defs   = [];
        $values = [];

        if (! Schema::hasTable('custom_field_definitions')) {
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

        if (! empty($defs)) {
            $defIds = array_column($defs, 'id');
            $vals   = DB::table('custom_field_values')
                ->whereIn('definition_id', $defIds)
                ->get();

            foreach ($vals as $v) {
                $values[$v->entity_id][$v->definition_id] = $v->value;
            }
        }

        return compact('defs', 'values');
    }

    /**
     * Loads field definitions and stored values for multiple object types
     * in exactly two database queries (regardless of the number of types).
     *
     * Suitable for controllers that need multiple types simultaneously,
     * e.g. ManagementController needs both 'management_function' and 'management_task'.
     *
     * @param  string[] $objectTypes  e.g. ['management_function', 'management_task']
     * @return array<string, array{defs: list<array<string, mixed>>, values: array<int, array<int, string>>}>
     *         ['management_function' => ['defs' => [...], 'values' => [...]], ...]
     */
    public function loadForObjectTypes(array $objectTypes): array
    {
        // Initialise result structure with empty arrays for each requested type
        $result = [];
        foreach ($objectTypes as $type) {
            $result[$type] = ['defs' => [], 'values' => []];
        }

        if (! Schema::hasTable('custom_field_definitions') || empty($objectTypes)) {
            return $result;
        }

        // Load all definitions for the requested types in one query
        $rawDefs = DB::table('custom_field_definitions')
            ->whereIn('object_type', $objectTypes)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        // Build a defId → objectType map for later value assignment
        $defIdToType = [];

        foreach ($rawDefs as $d) {
            $result[$d->object_type]['defs'][] = $this->normalizeDef($d);
            $defIdToType[$d->id]               = $d->object_type;
        }

        // Load all values for the resolved definition IDs in one query
        if (! empty($defIdToType)) {
            $vals = DB::table('custom_field_values')
                ->whereIn('definition_id', array_keys($defIdToType))
                ->get();

            foreach ($vals as $v) {
                $type = $defIdToType[$v->definition_id] ?? null;
                if ($type === null) {
                    continue;
                }
                $result[$type]['values'][$v->entity_id][$v->definition_id] = $v->value;
            }
        }

        return $result;
    }

    /**
     * Normalises a raw database record into the uniform field definition format.
     *
     * @param  object               $d  Raw stdClass row from custom_field_definitions
     * @return array<string, mixed>
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
