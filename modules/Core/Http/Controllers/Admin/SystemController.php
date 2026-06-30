<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * System overview and maintenance controller.
 *
 * Provides the admin system panel with environment information,
 * installed module status, and migration management.
 */
class SystemController extends Controller
{
    /**
     * @param ModuleLoader $moduleLoader
     */
    public function __construct(private readonly ModuleLoader $moduleLoader) {}

    /**
     * Renders the system overview page.
     *
     * Includes Laravel/PHP versions, database name, app URL,
     * installed modules, and the current migration status.
     *
     * @return View
     */
    public function index(): View
    {
        $installed        = $this->moduleLoader->getInstalled();
        $available        = $this->moduleLoader->detectAvailable();
        $laravelVersion   = app()->version();
        $phpVersion       = PHP_VERSION;
        $dbName           = config('database.connections.' . config('database.default') . '.database');
        $appUrl           = config('app.url');
        $installedAt      = file_exists(storage_path('installed'))
            ? trim(file_get_contents(storage_path('installed')))
            : null;

        $migrationsStatus = $this->getMigrationsStatus();

        return view('core::admin.system.index', compact(
            'installed',
            'available',
            'laravelVersion',
            'phpVersion',
            'dbName',
            'appUrl',
            'installedAt',
            'migrationsStatus'
        ));
    }

    /**
     * Runs all pending migrations and redirects back to the system overview.
     *
     * @return RedirectResponse
     */
    public function runMigrations(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return redirect()
            ->route('admin.system.index')
            ->with('success', 'Migrationen erfolgreich ausgeführt.');
    }

    /**
     * Returns the current migration status by running `migrate:status`.
     *
     * @return array{ok: bool, pending: int, output: string}
     */
    private function getMigrationsStatus(): array
    {
        try {
            Artisan::call('migrate:status', ['--no-interaction' => true]);
            $output  = Artisan::output();
            $pending = substr_count($output, 'Pending');

            return [
                'ok'      => $pending === 0,
                'pending' => $pending,
                'output'  => $output,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'pending' => 0, 'output' => ''];
        }
    }
}
