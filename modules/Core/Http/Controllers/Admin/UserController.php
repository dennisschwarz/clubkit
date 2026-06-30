<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Modules\Core\Http\Requests\StoreUserRequest;
use Modules\Core\Http\Requests\UpdateUserRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Manages system users (admins, coaches, etc.).
 *
 * A modal with two tabs is used:
 *   Tab 1 – Login info: name, email, password
 *   Tab 2 – Rights:     role selection or custom permission set
 */
class UserController extends Controller
{
    // ── index ─────────────────────────────────────────────────────────────────

    /**
     * @return View
     */
    public function index(): View
    {
        $users       = User::with('roles', 'permissions')->orderBy('name')->paginate(25);
        $roles       = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // Group permissions by module prefix (text before the first dot) for the UI
        $permsByModule = [];
        foreach ($permissions as $p) {
            $module = explode('.', $p->name)[0];
            $permsByModule[$module][] = $p->name;
        }
        ksort($permsByModule);

        // Build JS data bridge for users-modal.js
        // Note: no fn()/map() in @json() – must use manual foreach loops
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

        // Build roles with their permissions for the role dropdown
        $rolesJs = [];
        foreach ($roles as $r) {
            $permNames = [];
            foreach ($r->permissions as $p) {
                $permNames[] = $p->name;
            }
            $rolesJs[$r->name] = [
                'name'        => $r->name,
                'permissions' => $permNames,
                'isSystem'    => in_array($r->name, ['super-admin', 'admin', 'user'], true),
            ];
        }

        return view('core::admin.users.index', compact(
            'users', 'roles', 'permissions', 'permsByModule',
            'usersJs', 'rolesJs'
        ));
    }

    // ── store ─────────────────────────────────────────────────────────────────

    /**
     * Creates a new user account with a hashed password.
     *
     * @param  StoreUserRequest $request
     * @return RedirectResponse
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Nutzer „' . $request->input('name') . '" angelegt.');
    }

    // ── show ──────────────────────────────────────────────────────────────────

    /**
     * @param  User $user
     * @return View
     */
    public function show(User $user): View
    {
        $user->load('roles', 'permissions');
        $roles       = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('core::admin.users.show', compact('user', 'roles', 'permissions'));
    }

    // ── update ────────────────────────────────────────────────────────────────

    /**
     * Updates a user's login info (Tab 1) or rights (Tab 2).
     *
     * Tab 1 (login info): name, email, optional new password.
     * Tab 2 (rights):     rights_only=1 flag, role dropdown, permissions checkboxes.
     *
     * The 'custom' role option clears all roles and applies individual permissions.
     * An empty role string clears both roles and individual permissions.
     *
     * @param  UpdateUserRequest $request
     * @param  User              $user
     * @return RedirectResponse
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // ── Tab 2: rights update ───────────────────────────────────────────────
        if ($request->boolean('rights_only')) {

            $role = $request->input('role');

            if ($role === 'custom') {
                $user->syncRoles([]);
                $permissions = $request->input('permissions', []);
                $user->syncPermissions(is_array($permissions) ? $permissions : []);
            } elseif ($role && $role !== '') {
                $user->syncPermissions([]);
                $user->syncRoles([$role]);
            } else {
                $user->syncRoles([]);
                $user->syncPermissions([]);
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Rechte von „' . $user->name . '" aktualisiert.');
        }

        // ── Tab 1: login info update ───────────────────────────────────────────
        $data = [
            'name'  => $request->input('name'),
            'email' => $request->input('email'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Nutzer „' . $user->name . '" aktualisiert.');
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    /**
     * Deletes a user account. A user cannot delete their own account.
     *
     * @param  User $user
     * @return RedirectResponse
     */
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
