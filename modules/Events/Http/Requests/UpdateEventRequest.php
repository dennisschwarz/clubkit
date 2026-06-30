<?php

declare(strict_types=1);

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for updating an existing event.
 *
 * Rules are identical to StoreEventRequest. Kept as a separate class
 * so that future update-specific constraints can be added cleanly.
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * @return bool
     */
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
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at'   => ['required', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location'    => ['nullable', 'string', 'max:200'],
            'notes'       => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'         => 'Terminbezeichnung ist erforderlich.',
            'starts_at.required'     => 'Startdatum ist erforderlich.',
            'ends_at.after_or_equal' => 'Enddatum muss nach dem Startdatum liegen.',
        ];
    }
}
