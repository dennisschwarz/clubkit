<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verwaltung der System-Nutzer (Admins, Trainer, …).
 * Nutzerdaten + Rollen/Rechte in einem Modal mit zwei Tabs.
 */
class UserController extends Controller
{
    // ── index ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        $users       = User::with('roles', 'permissions')->orderBy('name')->paginate(25);
        $roles       = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // Data Bridge für users-modal.js
        // HINWEIS: Kein fn() / map() in @json() – manuell mit foreach aufbauen
        $usersJs = [];
        foreach ($users as $u) {
            $rolesArr       = [];
            $permissionsArr = [];

            foreach ($u->roles as $r) {
                $rolesArr[] = $r->name;
            }
            foreach ($u->permissions as $p) {
                $permissionsArr[] = $p->name;
            }

            $usersJs[$u->id] = [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'roles'       => $rolesArr,
                'permissions' => $permissionsArr,
            ];
        }

        return view('core::admin.users.index', compact('users', 'roles', 'permissions', 'usersJs'));
    }

    // ── store ──────────────────────────────────────────────────────────────

    /**
     * Neuen Nutzer anlegen.
     * Wird aus users-modal.js über routes.store aufgerufen (Tab 1: Login-Infos).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Nutzer „' . $validated['name'] . '" angelegt.');
    }

    // ── show ───────────────────────────────────────────────────────────────

    public function show(User $user): View
    {
        $user->load('roles', 'permissions');
        $roles       = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('core::admin.users.show', compact('user', 'roles', 'permissions'));
    }

    // ── update ─────────────────────────────────────────────────────────────

    /**
     * Nutzer aktualisieren.
     * Tab 1 (Login-Infos): name, email, optionales Passwort.
     * Tab 2 (Rechte): rights_only=1, role (Radio), permissions[] (Checkboxen).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // ── Tab 2: Rechte-Update ───────────────────────────────────────────
        if ($request->boolean('rights_only')) {

            $role = $request->input('role');

            if ($role === 'custom') {
                // Benutzerdefinierte Berechtigungen
                $user->syncRoles([]);
                $permissions = $request->input('permissions', []);
                $user->syncPermissions(is_array($permissions) ? $permissions : []);
            } elseif ($role && $role !== '') {
                // Standard-Rolle: alle Einzelberechtigungen entfernen
                $user->syncPermissions([]);
                $user->syncRoles([$role]);
            } else {
                // Keine Rolle: alle entfernen
                $user->syncRoles([]);
                $user->syncPermissions([]);
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Rechte von „' . $user->name . '" aktualisiert.');
        }

        // ── Tab 1: Login-Infos-Update ──────────────────────────────────────
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email,' . $user->id],
            'password'              => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable'],
        ]);

        $data = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Nutzer „' . $user->name . '" aktualisiert.');
    }

    // ── destroy ────────────────────────────────────────────────────────────

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Du kannst deinen eigenen Account nicht löschen.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Nutzer „' . $name . '" gelöscht.');
    }
}
