<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Http\Requests\StoreRoleRequest;
use Modules\Core\Http\Requests\UpdateRoleRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Manages system roles and their associated permissions.
 *
 * Roles (e.g. "Trainer", "Kassenwart") are created here and linked
 * to fine-grained permissions (e.g. "members.view", "teams.manage").
 * System roles (super-admin, admin, user) cannot be renamed or deleted.
 */
class RolesController extends Controller
{
    /**
     * Renders the roles overview with a list of all roles and permissions,
     * grouped by module prefix for the UI.
     *
     * @return View
     */
    public function index(): View
    {
        $roles       = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // Group permissions by module prefix (text before the first dot)
        $permsByModule = [];
        foreach ($permissions as $p) {
            $module = explode('.', $p->name)[0];
            $permsByModule[$module][] = $p;
        }
        ksort($permsByModule);

        // Build JS data bridge for roles-modal.js
        $rolesJs = [];
        foreach ($roles as $role) {
            $permNames = [];
            foreach ($role->permissions as $p) {
                $permNames[] = $p->name;
            }
            $rolesJs[$role->id] = [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $permNames,
            ];
        }

        return view('core::admin.roles.index', compact('roles', 'permissions', 'permsByModule', 'rolesJs'));
    }

    /**
     * Creates a new role with the given name and optional permissions.
     *
     * @param  StoreRoleRequest $request
     * @return RedirectResponse
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->input('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', __('roles.flash.created', ['name' => $role->name]));
    }

    /**
     * Updates a role's name and/or permissions.
     *
     * System roles (super-admin, admin, user) cannot be renamed —
     * only their permission set can be modified.
     *
     * @param  UpdateRoleRequest $request
     * @param  Role              $role
     * @return RedirectResponse
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if (! in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            $role->update(['name' => $request->input('name')]);
        }

        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', __('roles.flash.updated', ['name' => $role->name]));
    }

    /**
     * Deletes a custom role. System roles cannot be deleted.
     *
     * @param  Role $role
     * @return RedirectResponse
     */
    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            return back()->with('error', __('roles.flash.system_delete_forbidden'));
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', __('roles.flash.deleted', ['name' => $name]));
    }
}
