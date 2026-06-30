<?php

declare(strict_types=1);

namespace Modules\CustomFields\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for bulk-saving custom field values for one entity.
 *
 * The object_type and entity_id come from the route (not the form body),
 * so only the values map itself is validated here.
 * Per-field type validation (number format, date format, etc.) is intentionally
 * deferred to the view layer to keep the upsert endpoint generic.
 */
class UpsertFieldValuesRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'values'   => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
