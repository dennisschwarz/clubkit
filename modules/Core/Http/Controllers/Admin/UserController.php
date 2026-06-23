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

        return view('core::admin.users.index', compact('users', 'roles', 'permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ];

        if (!$request->boolean('rights_only')) {
            $validated = $request->validate($rules);

            $user = User::create([
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            $this->syncRightsFromRequest($request, $user);
        }

        return redirect()->route('admin.users.index')->with('success', 'Nutzer angelegt.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Nur Rechte aktualisieren
        if ($request->boolean('rights_only')) {
            $this->syncRightsFromRequest($request, $user);
            return redirect()->route('admin.users.index')->with('success', 'Rechte aktualisiert.');
        }

        // Login-Infos aktualisieren
        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', Password::min(8)->letters()->numbers(), 'confirmed'];
        }

        $validated = $request->validate($rules);

        $updateData = ['name' => $validated['name'], 'email' => $validated['email']];

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

    // ── Hilfsmethode ─────────────────────────────────────────────────────────

    private function syncRightsFromRequest(Request $request, User $user): void
    {
        $role = $request->input('role');

        if ($role && $role !== 'custom') {
            // Feste Systemrolle
            $user->syncPermissions([]);
            $user->syncRoles([$role]);
        } elseif ($role === 'custom') {
            // Benutzerdefiniert: keine Rolle, nur direkte Permissions
            $user->syncRoles([]);
            $user->syncPermissions($request->input('permissions', []));
        }
        // Wenn role leer → keine Änderung an Rechten (z.B. reiner Login-Update)
    }
}
