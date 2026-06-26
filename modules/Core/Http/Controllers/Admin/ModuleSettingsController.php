<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Modul-Einstellungen Hub.
 *
 * Rendert nur den Rahmen – die einzelnen Sektionen werden
 * über den Hook-Point 'admin.module-settings.sections' von
 * den jeweiligen Modulen registriert.
 */
class ModuleSettingsController extends Controller
{
    public function index(): View
    {
        return view('core::admin.module-settings.index');
    }
}
