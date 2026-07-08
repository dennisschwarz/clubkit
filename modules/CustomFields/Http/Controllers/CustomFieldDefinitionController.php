<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\CustomFields\Http\Requests\StoreFieldDefinitionRequest;
use Modules\CustomFields\Http\Requests\UpdateFieldDefinitionRequest;
use Modules\CustomFields\Models\CustomFieldDefinition;

/**
 * Handles CRUD for custom field definitions.
 *
 * Field definitions are managed on the Module Settings page, not on a
 * dedicated route. The index() method therefore redirects there.
 *
 * Validation is delegated to StoreFieldDefinitionRequest and UpdateFieldDefinitionRequest.
 * Both rules are identical today; the split exists so future update-specific constraints
 * (e.g. preventing field_type changes when values already exist) can be added cleanly.
 *
 * store() supports two response modes:
 *   - Regular POST → redirect to module settings (default)
 *   - AJAX (Accept: application/json) → JSON with id/slug/label
 *     Used by the Import mapping step to populate dropdowns dynamically.
 *
 * Activity logging is handled automatically via LogsActivity on the model.
 */
class CustomFieldDefinitionController extends Controller
{
    /**
     * Redirects to the module settings page where field management lives.
     *
     * @return RedirectResponse
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.module-settings.index');
    }

    /**
     * Creates a new custom field definition.
     *
     * Generates a unique slug from the label, parses the textarea options,
     * and assigns the next sort order in the object type's sequence.
     *
     * @param  StoreFieldDefinitionRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(StoreFieldDefinitionRequest $request): RedirectResponse|JsonResponse
    {
        $validated               = $request->validated();
        $validated['slug']       = $this->generateSlug($validated['label'], $validated['object_type']);
        $validated['options']    = $this->parseOptions($request->input('options_raw'), $validated['field_type']);
        $validated['sort_order'] = $this->nextSortOrder($validated['object_type']);
        $validated['created_by'] = auth()->id();

        $def = CustomFieldDefinition::create($validated);

        // AJAX request (e.g. Import mapping step): return JSON instead of redirect
        if ($request->wantsJson()) {
            return response()->json([
                'id'         => $def->id,
                'slug'       => $def->slug,
                'label'      => $def->label,
                'field_type' => $def->field_type,
            ]);
        }

        return redirect()->route('admin.module-settings.index')
            ->with('success', __('custom-fields.flash.created', ['name' => $def->label]));
    }

    /**
     * Updates an existing custom field definition.
     *
     * Re-generates the slug only when the label changes to avoid
     * breaking existing data that references the old slug.
     *
     * @param  UpdateFieldDefinitionRequest $request
     * @param  int                          $id
     * @return RedirectResponse
     */
    public function update(UpdateFieldDefinitionRequest $request, int $id): RedirectResponse
    {
        $def       = CustomFieldDefinition::findOrFail($id);
        $validated = $request->validated();

        if ($validated['label'] !== $def->label) {
            $validated['slug'] = $this->generateSlug($validated['label'], $validated['object_type'], $id);
        }

        $validated['options'] = $this->parseOptions($request->input('options_raw'), $validated['field_type']);

        $def->update($validated);

        return redirect()->route('admin.module-settings.index')
            ->with('success', __('custom-fields.flash.updated', ['name' => $def->label]));
    }

    /**
     * Deletes a custom field definition and all its values (via DB cascade).
     *
     * @param  int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $def   = CustomFieldDefinition::findOrFail($id);
        $label = $def->label;
        $def->delete();

        return redirect()->route('admin.module-settings.index')
            ->with('success', __('custom-fields.flash.deleted', ['name' => $label]));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generates a unique slug for the field within its object type.
     *
     * Replaces German umlauts before slugifying, then appends a numeric
     * suffix (_2, _3, …) if the base slug is already taken.
     *
     * @param  string   $label
     * @param  string   $objectType
     * @param  int|null $excludeId
     * @return string
     */
    private function generateSlug(string $label, string $objectType, ?int $excludeId = null): string
    {
        $map  = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'];
        $base = Str::slug(str_replace(array_keys($map), array_values($map), $label), '_');
        $slug = $base;
        $i    = 2;

        $query = CustomFieldDefinition::where('object_type', $objectType)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->clone()->exists()) {
            $slug  = $base . '_' . $i++;
            $query = CustomFieldDefinition::where('object_type', $objectType)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Parses a textarea string of newline-separated option values.
     *
     * Returns null for non-select field types or when the input is empty.
     *
     * @param  string|null $raw
     * @param  string      $fieldType
     * @return list<string>|null
     */
    private function parseOptions(?string $raw, string $fieldType): ?array
    {
        if ($fieldType !== 'select' || empty($raw)) {
            return null;
        }

        $lines = array_filter(
            array_map('trim', explode("\n", $raw)),
            fn ($line) => $line !== ''
        );

        return array_values($lines) ?: null;
    }

    /**
     * Returns the next sort_order value for the given object type.
     *
     * Increments by 10 from the current maximum to leave room for reordering.
     *
     * @param  string $objectType
     * @return int
     */
    private function nextSortOrder(string $objectType): int
    {
        $max = CustomFieldDefinition::where('object_type', $objectType)->max('sort_order') ?? 0;
        return $max + 10;
    }
}
