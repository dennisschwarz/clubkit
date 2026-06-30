<?php

declare(strict_types=1);

namespace Modules\Import\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the column mapping form submission in step 2 of the import wizard.
 *
 * Authorization is delegated to route middleware (permission:import.execute).
 * The 'mapping' key is an associative array of CSV column names → member field names.
 * Individual keys cannot be validated statically (CSV columns are runtime-determined),
 * but the structure is enforced as a key-value string array.
 */
class SaveMappingRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mapping'   => ['present', 'array'],
            'mapping.*' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapping.present' => 'Die Spaltenzuordnung fehlt.',
            'mapping.array'   => 'Die Spaltenzuordnung muss ein Array sein.',
        ];
    }
}
