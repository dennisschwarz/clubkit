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
use Spatie\Activitylog\Models\Activity;

/**
 * Boots the Core module of ClubKit.
 *
 * Responsibilities:
 *  - Registers the Hook system as a singleton available to all modules
 *  - Loads routes, views, and database migrations
 *  - Registers anonymous Blade components under the 'ck' prefix
 *  - Registers the @ckHook Blade directive for the extension-point system
 *  - Shares global application settings with all views
 *  - Attaches the request IP address to every activity log entry
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        // Hook system singleton – globally accessible to all modules via app('ck.hooks')
        $this->app->singleton('ck.hooks', fn () => new HookRegistry());
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerBladeComponents();
        $this->registerHookDirective();
        $this->shareSettings();
        $this->configureActivityLog();
    }

    // ── Routes ────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    private function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/routes.php');
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'core');
    }

    // ── Migrations ────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    // ── Blade Components ──────────────────────────────────────────────────────

    /**
     * Registers anonymous Blade components from modules/Core/Resources/Views/components/
     * under the 'ck' namespace, making them available as <x-ck-button>, <x-ck-modal>, etc.
     *
     * @return void
     */
    private function registerBladeComponents(): void
    {
        Blade::anonymousComponentPath(
            __DIR__ . '/Resources/Views/components',
            'ck'
        );
    }

    // ── Hook Directive ────────────────────────────────────────────────────────

    /**
     * Registers the @ckHook('extension.point') Blade directive.
     *
     * Renders all views that modules have registered for the given extension point.
     * All variables currently in scope are automatically passed to the hook views,
     * so modules do not need to receive data explicitly.
     *
     * Examples:
     *   @ckHook('member.modal.tabs')
     *   @ckHook('member.table.row')   ← $member is available inside the @foreach scope
     *
     * @return void
     */
    private function registerHookDirective(): void
    {
        Blade::directive('ckHook', function (string $expression): string {
            // Filter internal Blade/PHP scope variables from the data passed to hook views
            $exclude = "['__data', '__path', '__env', '__ob_level__', '__ckHookView']";

            return "<?php foreach (app('ck.hooks')->get({$expression}) as \$__ckHookView): " .
                   "echo view(\$__ckHookView, " .
                   "\\Illuminate\\Support\\Arr::except(get_defined_vars(), {$exclude}))->render(); " .
                   "endforeach; ?>";
        });
    }

    // ── Global Settings ───────────────────────────────────────────────────────

    /**
     * Loads settings from the database and shares them as $ckSettings with all views.
     * Fails gracefully when the settings table does not yet exist (e.g. fresh installation).
     *
     * @return void
     */
    private function shareSettings(): void
    {
        $settings = [];

        if (Schema::hasTable('settings')) {
            $settings = Setting::allCached();
        }

        View::share('ckSettings', $settings);
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures the Spatie Activity Log for ClubKit.
     *
     * Attaches the request IP address to every activity log entry via
     * a model saving event. This runs globally for all modules that use
     * the LogsActivity trait, requiring no per-model configuration.
     *
     * v5 schema note:
     *   The IP is stored in the `properties` column, which in v5 holds
     *   only custom user data. Tracked model attribute changes live in the
     *   separate `attribute_changes` column and are not affected by this hook.
     *
     * @return void
     */
    private function configureActivityLog(): void
    {
        Activity::saving(function (Activity $activity): void {
            $activity->properties = $activity->properties->merge([
                'ip' => request()->ip(),
            ]);
        });
    }
}
