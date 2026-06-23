<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function __construct(private readonly ModuleLoader $moduleLoader)
    {
    }

    public function index(): View
    {
        $installed    = $this->moduleLoader->getInstalled();
        $available    = $this->moduleLoader->detectAvailable();
        $laravelVersion = app()->version();
        $phpVersion   = PHP_VERSION;
        $dbName       = config('database.connections.' . config('database.default') . '.database');
        $appUrl       = config('app.url');
        $installedAt  = file_exists(storage_path('installed'))
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

    public function runMigrations(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return redirect()
            ->route('admin.system.index')
            ->with('success', 'Migrationen erfolgreich ausgeführt.');
    }

    private function getMigrationsStatus(): array
    {
        try {
            $result = Artisan::call('migrate:status', ['--no-interaction' => true]);
            $output = Artisan::output();

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
