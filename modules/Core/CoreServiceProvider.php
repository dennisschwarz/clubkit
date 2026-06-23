<?php

declare(strict_types=1);

namespace Modules\Core;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Models\Setting;
use Modules\Core\Services\HookRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hook-System als Singleton – steht allen Modulen global zur Verfügung
        $this->app->singleton('ck.hooks', fn () => new HookRegistry());
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerBladeComponents();
        $this->registerHookDirective();
        $this->shareSettings();
    }

    // ── Routen ────────────────────────────────────────────────────────────

    private function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/routes.php');
    }

    // ── Views ─────────────────────────────────────────────────────────────

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'core');
    }

    // ── Migrationen ───────────────────────────────────────────────────────

    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    // ── Blade Components ──────────────────────────────────────────────────

    /**
     * Anonyme Blade-Components unter modules/Core/Resources/Views/components/
     * als <x-ck-*> registrieren (z. B. <x-ck-button>, <x-ck-modal>).
     */
    private function registerBladeComponents(): void
    {
        Blade::anonymousComponentPath(
            __DIR__ . '/Resources/Views/components',
            'ck'
        );
    }

    // ── Hook-Direktive ────────────────────────────────────────────────────

    /**
     * @ckHook('extension.point') – rendert alle registrierten Hook-Views
     * für den angegebenen Extension Point.
     *
     * Alle aktuellen View-Variablen (z. B. $member, $members) werden automatisch
     * an die Hook-Views weitergegeben. Kein manuelles Übergeben von Daten nötig.
     *
     * Beispiele:
     *   @ckHook('member.modal.tabs')
     *   @ckHook('member.table.row')   ← $member ist im @foreach-Scope verfügbar
     */
    private function registerHookDirective(): void
    {
        Blade::directive('ckHook', function (string $expression): string {
            // Interne PHP/Blade-Variablen aus dem Scope herausfiltern
            $exclude = "['__data', '__path', '__env', '__ob_level__', '__ckHookView']";

            return "<?php foreach (app('ck.hooks')->get({$expression}) as \$__ckHookView): " .
                   "echo view(\$__ckHookView, " .
                   "\\Illuminate\\Support\\Arr::except(get_defined_vars(), {$exclude}))->render(); " .
                   "endforeach; ?>";
        });
    }

    // ── Globale Settings ──────────────────────────────────────────────────

    /**
     * Settings aus der Datenbank laden und als $ckSettings mit allen Views teilen.
     * Wenn die settings-Tabelle noch nicht existiert (Erst-Installation), bleibt das Array leer.
     */
    private function shareSettings(): void
    {
        $settings = [];

        if (Schema::hasTable('settings')) {
            $settings = Setting::allCached();
        }

        View::share('ckSettings', $settings);
    }
}
