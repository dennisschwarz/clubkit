<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Module settings hub controller.
 *
 * Renders only the outer shell of the module-settings admin page.
 * The individual settings sections are injected by each installed module
 * via the 'admin.module-settings.sections' hook extension point.
 */
class ModuleSettingsController extends Controller
{
    /**
     * @return View
     */
    public function index(): View
    {
        return view('core::admin.module-settings.index');
    }
}
