<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verwaltung der Systemrollen und ihrer Permissions.
 * Rollen (z.B. "Trainer", "Kassenwart") werden hier angelegt und
 * mit Permissions (z.B. "members.view") verknüpft.
 */
class RolesController extends Controller
{
    public function index(): View
    {
        $roles       = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // Permissions nach Modul gruppieren (Präfix vor dem ersten Punkt)
        $permsByModule = [];
        foreach ($permissions as $p) {
            $module = explode('.', $p->name)[0];
            $permsByModule[$module][] = $p;
        }
        ksort($permsByModule);

        // JS-Daten aufbereiten
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rolle „' . $role->name . '" angelegt.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // Systemrollen (super-admin, admin, user) dürfen nicht umbenannt werden
        if (in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            $validated = $request->validate([
                'permissions'   => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);
        } else {
            $validated = $request->validate([
                'name'          => ['required', 'string', 'max:100', 'unique:roles,name,' . $role->id],
                'permissions'   => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rolle „' . $role->name . '" aktualisiert.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Systemrollen schützen
        if (in_array($role->name, ['super-admin', 'admin', 'user'], true)) {
            return back()->with('error', 'Systemrollen können nicht gelöscht werden.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rolle „' . $name . '" gelöscht.');
    }
}
