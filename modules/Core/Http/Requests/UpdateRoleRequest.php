<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the data for updating an existing role.
 *
 * The `name` field uses `sometimes` because system roles (super-admin, admin, user)
 * do not send a name field — the controller handles the rename guard separately.
 *
 * Permission is enforced at the route level via middleware('permission:core.manage').
 */
class UpdateRoleRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name'          => ['sometimes', 'required', 'string', 'max:100', 'unique:roles,name,' . $roleId],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Rollenname ist erforderlich.',
            'name.max'      => 'Der Rollenname darf maximal 100 Zeichen lang sein.',
            'name.unique'   => 'Eine Rolle mit diesem Namen existiert bereits.',
        ];
    }
}
