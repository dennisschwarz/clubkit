<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Services\CustomFieldRegistry;

class CustomFieldDefinitionController extends Controller
{
    /**
     * Weiterleitung auf die Modul-Einstellungsseite.
     * Die Feldverwaltung findet direkt in Modul-Einstellungen statt.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.module-settings.index');
    }

    /**
     * Neues Custom Field anlegen.
     *
     * Unterstützt zwei Response-Modi:
     *  - Normaler POST → Redirect zurück zu Modul-Einstellungen (Standard)
     *  - AJAX (Accept: application/json) → JSON mit id/slug/label zurück
     *    → Wird vom Import-Mapping-Schritt genutzt, um Dropdowns dynamisch zu befüllen.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated               = $this->validateDefinition($request);
        $validated['slug']       = $this->generateSlug($validated['label'], $validated['object_type']);
        $validated['options']    = $this->parseOptions($request->input('options_raw'), $validated['field_type']);
        $validated['sort_order'] = $this->nextSortOrder($validated['object_type']);
        $validated['created_by'] = auth()->id();

        $def = CustomFieldDefinition::create($validated);

        // AJAX-Anfrage (z.B. Import-Mapping): JSON zurückgeben statt Redirect
        if ($request->wantsJson()) {
            return response()->json([
                'id'         => $def->id,
                'slug'       => $def->slug,
                'label'      => $def->label,
                'field_type' => $def->field_type,
            ]);
        }

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Feld „' . $validated['label'] . '" angelegt.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $def       = CustomFieldDefinition::findOrFail($id);
        $validated = $this->validateDefinition($request, $id);

        if ($validated['label'] !== $def->label) {
            $validated['slug'] = $this->generateSlug($validated['label'], $validated['object_type'], $id);
        }

        $validated['options'] = $this->parseOptions($request->input('options_raw'), $validated['field_type']);

        $def->update($validated);

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Feld „' . $validated['label'] . '" aktualisiert.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $def   = CustomFieldDefinition::findOrFail($id);
        $label = $def->label;
        $def->delete();

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Feld „' . $label . '" gelöscht.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function validateDefinition(Request $request, ?int $excludeId = null): array
    {
        $objectTypes = array_keys(CustomFieldRegistry::availableObjectTypes());
        $fieldTypes  = array_keys(CustomFieldRegistry::fieldTypes());

        return $request->validate([
            'object_type' => ['required', 'string', Rule::in($objectTypes)],
            'label'       => ['required', 'string', 'max:100'],
            'field_type'  => ['required', 'string', Rule::in($fieldTypes)],
            'options_raw' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:200'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function generateSlug(string $label, string $objectType, ?int $excludeId = null): string
    {
        $map  = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue'];
        $base = Str::slug(str_replace(array_keys($map), array_values($map), $label), '_');
        $slug = $base;
        $i    = 2;

        $query = CustomFieldDefinition::where('object_type', $objectType)->where('slug', $slug);
        if ($excludeId) $query->where('id', '!=', $excludeId);

        while ($query->clone()->exists()) {
            $slug  = $base . '_' . $i++;
            $query = CustomFieldDefinition::where('object_type', $objectType)->where('slug', $slug);
            if ($excludeId) $query->where('id', '!=', $excludeId);
        }

        return $slug;
    }

    private function parseOptions(?string $raw, string $fieldType): ?array
    {
        if ($fieldType !== 'select' || empty($raw)) return null;

        $lines = array_filter(
            array_map('trim', explode("\n", $raw)),
            fn($line) => $line !== ''
        );

        return array_values($lines) ?: null;
    }

    private function nextSortOrder(string $objectType): int
    {
        $max = CustomFieldDefinition::where('object_type', $objectType)->max('sort_order') ?? 0;
        return $max + 10;
    }
}
