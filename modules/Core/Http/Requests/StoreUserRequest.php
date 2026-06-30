<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the data for creating a new system user.
 *
 * Permission is enforced at the route level via middleware('permission:core.manage').
 */
class StoreUserRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'                  => 'Name ist erforderlich.',
            'email.required'                 => 'E-Mail-Adresse ist erforderlich.',
            'email.email'                    => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            'email.unique'                   => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'password.required'              => 'Passwort ist erforderlich.',
            'password.min'                   => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed'             => 'Die Passwörter stimmen nicht überein.',
            'password_confirmation.required' => 'Passwortwiederholung ist erforderlich.',
        ];
    }
}
