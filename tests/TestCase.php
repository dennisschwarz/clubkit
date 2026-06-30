<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base TestCase for all ClubKit tests.
 *
 * Handles module migrations and route/view cache clearing before each test.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ── 1. Clear compiled views ────────────────────────────────────────────
        // Compiled Blade views may contain stale route() calls.
        Artisan::call('view:clear');

        // ── 2. Run module migrations ───────────────────────────────────────────
        // Each module's migration directory is passed individually so that
        // the --realpath flag resolves absolute filesystem paths correctly.
        // NOTE: Do NOT replace with $migrator->path() + single migrate call —
        // that pattern does not respect --realpath and can miss migrations.
        // See ARBEITSREGELN REGEL 5.
        foreach (glob(base_path('modules/*/Database/Migrations')) as $path) {
            Artisan::call('migrate', ['--path' => $path, '--realpath' => true]);
        }
    }

    /**
     * Clear the route cache before bootstrapping the application.
     *
     * NOTE: base_path() is not available here — use dirname(__DIR__) instead.
     */
    protected function refreshApplication(): void
    {
        $projectRoot = dirname(__DIR__);

        foreach (glob($projectRoot . '/bootstrap/cache/routes*.php') ?: [] as $file) {
            @unlink($file);
        }

        parent::refreshApplication();
    }
}
