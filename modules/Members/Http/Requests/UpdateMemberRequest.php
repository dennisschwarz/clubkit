<?php

declare(strict_types=1);

namespace Modules\Members\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the request data for updating an existing member.
 *
 * Kept as a separate class from StoreMemberRequest so that
 * update-specific rules (e.g. unique pass_number ignoring the current record)
 * can be applied without touching the controller.
 */
class UpdateMemberRequest extends FormRequest
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
        /** @var \Modules\Members\Models\Member $member */
        $member = $this->route('member');

        return [
            'first_name'            => ['required', 'string', 'max:100'],
            'last_name'             => ['required', 'string', 'max:100'],
            'pass_number'           => [
                'nullable',
                'string',
                'max:30',
                // Ignore the current member's own pass_number on uniqueness check.
                Rule::unique('members', 'pass_number')->ignore($member?->id),
            ],
            'gender'                => ['nullable', 'in:male,female,diverse'],
            'date_of_birth'         => ['nullable', 'date', 'before:today'],
            'status'                => ['required', 'in:active,inactive'],
            'eligible_to_play_date' => ['nullable', 'date'],
            'profile_image'         => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required'  => 'Vorname ist erforderlich.',
            'last_name.required'   => 'Nachname ist erforderlich.',
            'pass_number.unique'   => 'Diese Passnummer ist bereits vergeben.',
            'pass_number.max'      => 'Die Passnummer darf maximal 30 Zeichen lang sein.',
            'gender.in'            => 'Geschlecht muss männlich, weiblich oder divers sein.',
            'date_of_birth.before' => 'Das Geburtsdatum muss in der Vergangenheit liegen.',
            'status.required'      => 'Status ist erforderlich.',
            'status.in'            => 'Status muss aktiv oder inaktiv sein.',
        ];
    }
}
