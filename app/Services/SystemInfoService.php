<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SystemInfoService
{
    /**
     * Sammelt alle relevanten System-Informationen.
     */
    public function collect(): array
    {
        return [
            'clubkit_version' => $this->clubkitVersion(),
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
            'php_sapi'        => PHP_SAPI,
            'db_status'       => $this->dbStatus(),
            'db_name'         => config('database.connections.mysql.database'),
            'db_driver'       => config('database.default'),
            'installed_at'    => $this->installedAt(),
            'modules'         => $this->installedModules(),
            'env'             => app()->environment(),
            'debug'           => config('app.debug'),
            'app_url'         => config('app.url'),
        ];
    }

    /**
     * Prüft ob ausstehende Migrations vorhanden sind.
     */
    public function hasPendingMigrations(): bool
    {
        try {
            $pending = DB::table('migrations')->pluck('migration')->toArray();
            $files   = glob(database_path('migrations/*.php')) ?: [];

            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($name, $pending, true)) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Führt ausstehende Migrations über Laravel Kernel aus (kein CLI).
     */
    public function runMigrations(): array
    {
        try {
            $app    = app();
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $exit   = $kernel->call('migrate', ['--force' => true]);
            $output = trim($kernel->output());

            return ['ok' => $exit === 0, 'message' => $output];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Privat ────────────────────────────────────────────────────────────────

    private function clubkitVersion(): string
    {
        $markerPath = storage_path('installed');
        if (!file_exists($markerPath)) return 'unbekannt';

        $content = file_get_contents($markerPath);
        preg_match('/Version:\s*(.+)/', $content, $m);
        return trim($m[1] ?? 'unbekannt');
    }

    private function installedAt(): ?string
    {
        $markerPath = storage_path('installed');
        if (!file_exists($markerPath)) return null;

        $content = file_get_contents($markerPath);
        preg_match('/Installed:\s*(.+)/', $content, $m);
        return trim($m[1] ?? '');
    }

    private function installedModules(): array
    {
        $modules = config('clubkit.modules', '');
        if (!$modules) return [];
        return array_filter(explode(',', $modules));
    }

    private function dbStatus(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
