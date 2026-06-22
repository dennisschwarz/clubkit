<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemInfoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SystemController extends Controller
{
    public function __construct(
        private readonly SystemInfoService $systemInfo,
    ) {}

    /**
     * System-Übersicht anzeigen.
     */
    public function index(): View
    {
        return view('admin.system.index', [
            'info'        => $this->systemInfo->collect(),
            'hasPending'  => $this->systemInfo->hasPendingMigrations(),
        ]);
    }

    /**
     * Ausstehende Migrations via Laravel Kernel ausführen (kein CLI nötig).
     */
    public function runMigrations(Request $request): RedirectResponse
    {
        $result = $this->systemInfo->runMigrations();

        if ($result['ok']) {
            return redirect()
                ->route('admin.system.index')
                ->with('success', 'Migrations erfolgreich ausgeführt.');
        }

        return redirect()
            ->route('admin.system.index')
            ->with('error', 'Migration fehlgeschlagen: ' . $result['message']);
    }
}
