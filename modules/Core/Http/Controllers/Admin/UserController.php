<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users       = User::with(['roles', 'permissions'])->orderBy('name')->paginate(25);
        $roles       = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        // JS-Daten sauber im Controller aufbereiten
        $usersJs = [];
        foreach ($users as $u) {
            $usersJs[$u->id] = [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'roles'       => $u->roles->pluck('name')->toArray(),
                'permissions' => $u->permissions->pluck('name')->toArray(),
            ];
        }

        return view('core::admin.users.index', compact('users', 'roles', 'permissions', 'usersJs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $this->syncRights($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Nutzer angelegt.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($request->boolean('rights_only')) {
            $this->syncRights($request, $user);
            return redirect()->route('admin.users.index')->with('success', 'Rechte aktualisiert.');
        }

        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', Password::min(8)->letters()->numbers(), 'confirmed'];
        }

        $validated   = $request->validate($rules);
        $updateData  = ['name' => $validated['name'], 'email' => $validated['email']];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('admin.users.index')->with('success', 'Nutzer aktualisiert.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Du kannst deinen eigenen Account nicht löschen.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Nutzer gelöscht.');
    }

    private function syncRights(Request $request, User $user): void
    {
        $role = $request->input('role');

        if ($role && $role !== 'custom') {
            $user->syncPermissions([]);
            $user->syncRoles([$role]);
        } elseif ($role === 'custom') {
            $user->syncRoles([]);
            $user->syncPermissions($request->input('permissions', []));
        }
    }
}
