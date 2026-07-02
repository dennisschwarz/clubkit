<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\Setting;

/**
 * Speichert die Core-Moduleinstellungen aus der Modul-Einstellungen-Seite.
 */
class CoreSettingsController extends Controller
{
    /**
     * Einstellungen speichern.
     *
     * Checkbox-Normalisation: Nicht gesendete Checkboxen (= deaktiviert)
     * kommen nicht im Request an, daher explizit auf '0' setzen.
     *
     * @param  Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        Setting::setValue(
            'registration_enabled',
            $request->boolean('registration_enabled') ? '1' : '0'
        );

        return back()->with('success', 'Core-Einstellungen gespeichert.');
    }
}
