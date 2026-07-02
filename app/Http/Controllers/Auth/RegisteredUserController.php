<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Models\Setting;

/**
 * Handles new user registration via the public registration form.
 */
class RegisteredUserController extends Controller
{
    /**
     * Prüft, ob die Registrierung vom Admin aktiviert wurde.
     * Gibt false zurück wenn die settings-Tabelle noch nicht existiert (Safety-Guard).
     */
    private function registrationEnabled(): bool
    {
        return Schema::hasTable('settings')
            && Setting::getValue('registration_enabled', '0') === '1';
    }

    /**
     * Display the registration view.
     *
     * @return View|RedirectResponse
     */
    public function create(): View|RedirectResponse
    {
        if (! $this->registrationEnabled()) {
            return redirect()->route('welcome')
                ->with('status', 'Die Registrierung ist derzeit nicht aktiviert.');
        }

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  Request $request
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $this->registrationEnabled()) {
            return redirect()->route('welcome')
                ->with('status', 'Die Registrierung ist derzeit nicht aktiviert.');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
