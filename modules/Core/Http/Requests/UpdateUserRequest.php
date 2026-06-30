<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the data for updating a system user.
 *
 * The update form has two tabs that POST to the same route:
 *
 *   Tab 1 — Login info (rights_only absent / false):
 *     name, email, optional new password.
 *
 *   Tab 2 — Rights (rights_only = 1):
 *     role selection or custom permission set.
 *     Name and email are not sent — no rules needed for them.
 *
 * Permission is enforced at the route level via middleware('permission:core.manage').
 */
class UpdateUserRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        // Tab 2: rights update — only role / permissions fields matter
        if ($this->boolean('rights_only')) {
            return [
                'role'          => ['nullable', 'string'],
                'permissions'   => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ];
        }

        // Tab 1: login info update
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password'              => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'      => 'Name ist erforderlich.',
            'email.required'     => 'E-Mail-Adresse ist erforderlich.',
            'email.email'        => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            'email.unique'       => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'password.min'       => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
        ];
    }
}
