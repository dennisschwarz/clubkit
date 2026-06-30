<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the request data for creating a new team.
 *
 * Authorization is delegated to route middleware (permission gates).
 * The ALLOWED_COLORS constant mirrors the CSS palette defined in base.css
 * and is shared by UpdateTeamRequest to keep the two in sync.
 */
class StoreTeamRequest extends FormRequest
{
    /** Allowed team color slugs – must match the --ck-team-* tokens in base.css. */
    public const ALLOWED_COLORS = [
        'blue', 'navy', 'green', 'teal', 'red',
        'orange', 'amber', 'purple', 'pink', 'slate',
    ];

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
            'color'          => ['nullable', Rule::in(self::ALLOWED_COLORS)],
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
