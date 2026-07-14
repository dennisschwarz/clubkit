<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

/**
 * Validates the request data for updating an existing management function.
 *
 * The exists:teams,id rule is conditional: only applied when Teams is installed.
 */
class UpdateFunctionRequest extends FormRequest
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
        $teamIdRule = array_values(array_filter([
            'integer',
            Schema::hasTable('teams') ? 'exists:teams,id' : null,
        ]));

        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => $teamIdRule,
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Name der Funktion ist erforderlich.',
            'team_ids.*.exists'    => 'Ein gewähltes Team existiert nicht.',
            'member_ids.*.exists'  => 'Ein gewähltes Mitglied existiert nicht.',
        ];
    }
}
