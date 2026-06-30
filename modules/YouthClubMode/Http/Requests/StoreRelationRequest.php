<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for creating a family relation between two members.
 *
 * The UI sends a 'relationship' value that encodes both the type and the direction
 * (e.g. 'father_of' means the current member IS the father).
 * Direction normalisation into the canonical DB form is handled by
 * FamilyController::resolveDirection() after this request passes.
 *
 * Authorization is delegated to route middleware.
 */
class StoreRelationRequest extends FormRequest
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
            'relationship'      => ['required', 'string', 'in:father,mother,father_of,mother_of,sibling'],
            'related_member_id' => ['required', 'integer', 'exists:members,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'relationship.required'      => 'Beziehungstyp ist erforderlich.',
            'relationship.in'            => 'Ungültiger Beziehungstyp.',
            'related_member_id.required' => 'Bitte ein Mitglied auswählen.',
            'related_member_id.exists'   => 'Das gewählte Mitglied existiert nicht.',
        ];
    }
}
