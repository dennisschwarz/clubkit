<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for adding a member to a team.
 *
 * Business rules beyond basic validation (eligible_only check, duplicate check)
 * are enforced in TeamController::addMember() after this request passes.
 */
class StoreTeamMemberRequest extends FormRequest
{
    /**
     * @return bool
     */
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
            'member_id'    => ['required', 'exists:members,id'],
            'squad_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_id.required' => 'Bitte ein Mitglied auswählen.',
            'member_id.exists'   => 'Das gewählte Mitglied existiert nicht.',
            'squad_number.min'   => 'Rückennummer muss zwischen 1 und 99 liegen.',
            'squad_number.max'   => 'Rückennummer muss zwischen 1 und 99 liegen.',
        ];
    }
}
