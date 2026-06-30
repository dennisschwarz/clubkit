<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the request data for updating an existing team.
 *
 * Shares ALLOWED_COLORS with StoreTeamRequest to avoid duplication.
 * Kept as a separate class so that future update-specific rules
 * (e.g. unique name ignoring the current record) can be added without
 * touching the store request.
 */
class UpdateTeamRequest extends FormRequest
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
            'name'           => ['required', 'string', 'max:100'],
            'color'          => ['nullable', Rule::in(StoreTeamRequest::ALLOWED_COLORS)],
            'is_competition' => ['nullable', 'boolean'],
            'eligible_only'  => ['nullable', 'boolean'],
            'season'         => ['nullable', 'string', 'max:20'],
            'league'         => ['nullable', 'string', 'max:100'],
            'age_class'      => ['nullable', 'string', 'max:50'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Teamname ist erforderlich.',
            'color.in'      => 'Ungültige Teamfarbe.',
        ];
    }
}
