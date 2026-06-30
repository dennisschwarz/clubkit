<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates data for creating a new treasury account.
 */
class StoreAccountRequest extends FormRequest
{
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
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', 'integer', 'exists:treasury_accounts,id'],
            'visibility'  => ['required', 'in:public,team_restricted'],
            // team_ids is only required when visibility = team_restricted
            'team_ids'    => ['nullable', 'array'],
            'team_ids.*'  => ['integer', 'exists:teams,id'],
        ];
    }
}
