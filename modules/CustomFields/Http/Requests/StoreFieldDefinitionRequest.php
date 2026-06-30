<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CustomFields\Services\CustomFieldRegistry;

/**
 * Validates the request data for creating a new custom field definition.
 *
 * Available object types and field types are resolved at runtime from
 * CustomFieldRegistry so the rules automatically reflect which modules
 * are currently installed.
 */
class StoreFieldDefinitionRequest extends FormRequest
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
