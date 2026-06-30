<?php

declare(strict_types=1);

namespace Modules\Members\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for creating a new member.
 *
 * Authorization is delegated to route middleware (permission gates).
 * This class is responsible only for input validation.
 */
class StoreMemberRequest extends FormRequest
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
            'first_name'            => ['required', 'string', 'max:100'],
            'last_name'             => ['required', 'string', 'max:100'],
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
            'gender.in'            => 'Geschlecht muss männlich, weiblich oder divers sein.',
            'date_of_birth.before' => 'Das Geburtsdatum muss in der Vergangenheit liegen.',
            'status.required'      => 'Status ist erforderlich.',
            'status.in'            => 'Status muss aktiv oder inaktiv sein.',
        ];
    }
}
