<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Core\Models\Setting;

/**
 * Zeigt die öffentliche Willkommensseite (Startseite).
 *
 * Eingeloggte Benutzer werden sofort zum Dashboard weitergeleitet.
 * Gäste sehen Login- und (optional) Registrierungsbutton.
 */
class WelcomeController extends Controller
{
    /**
     * Single-Action-Controller: Willkommensseite anzeigen oder zum Dashboard weiterleiten.
     *
     * @return View|RedirectResponse
     */
    public function __invoke(): View|RedirectResponse
    {
        // Eingeloggte User → direkt zum Dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Registrierung ist nur aktiv, wenn Setting gesetzt und Tabelle vorhanden
        $registrationEnabled = Schema::hasTable('settings')
            && Setting::getValue('registration_enabled', '0') === '1';

        return view('core::welcome', compact('registrationEnabled'));
    }
}
