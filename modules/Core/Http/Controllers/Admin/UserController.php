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
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Manages system users (admins, coaches, etc.).
 *
 * Allowed sort fields (via ?sort=... | ?sort=-...):
 *   name (default ASC), email, created_at
 *
 * allowedSorts() accepts variadic args — NO array wrapper.
 *
 * Rights management supports two modes via the rights_only flag:
 *   role = 'custom'  → sync direct permissions, clear roles
 *   role = <name>    → sync role, clear direct permissions
 *   role = ''        → clear both roles and direct permissions
 */
class UserController extends Controller
{
    // ── index ─────────────────────────────────────────────────────────────────

    /**
     * Display the paginated user list with roles, permissions and JS data bridges.
     *
     * @return View
     */
    public function index(): View
    {
        $users = QueryBuilder::for(User::class)
            ->with('roles', 'permissions')
            ->allowedSorts('name', 'email', 'created_at')
            ->defaultSort('name')
            ->paginate(25)
            ->withQueryString();

        $roles       = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // Group permissions by module prefix for the role editor UI.
        $permsByModule = [];
        foreach ($permissions as $p) {
            $module = explode('.', $p->name)[0];
            $permsByModule[$module][] = $p->name;
        }
        ksort($permsByModule);

        // JS data bridge for users-modal.js — foreach only, no arrow functions.
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
     * Store a newly created user.
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
     * Display the user detail / rights management page.
     *
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
     * Update user profile data or sync rights (role + direct permissions).
     * Determined by the presence of the rights_only boolean flag in the request.
     *
     * @param  UpdateUserRequest $request
     * @param  User              $user
     * @return RedirectResponse
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
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
     * Delete a user. Prevents self-deletion.
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
