<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CustomFields\Services\CustomFieldRegistry;

/**
 * Validates the request data for updating an existing custom field definition.
 *
 * Rules are identical to StoreFieldDefinitionRequest. Kept as a separate class
 * so that future update-specific rules (e.g. preventing field_type changes when
 * values already exist) can be added without touching the store request.
 */
class UpdateFieldDefinitionRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'object_type' => ['required', 'string', Rule::in(array_keys(CustomFieldRegistry::availableObjectTypes()))],
            'label'       => ['required', 'string', 'max:100'],
            'field_type'  => ['required', 'string', Rule::in(array_keys(CustomFieldRegistry::fieldTypes()))],
            'options_raw' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:200'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'object_type.required' => 'Objekt-Typ ist erforderlich.',
            'object_type.in'       => 'Ungültiger Objekt-Typ.',
            'label.required'       => 'Feldbezeichnung ist erforderlich.',
            'field_type.required'  => 'Feldtyp ist erforderlich.',
            'field_type.in'        => 'Ungültiger Feldtyp.',
        ];
    }
}
