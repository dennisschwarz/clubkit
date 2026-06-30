<?php

declare(strict_types=1);

namespace Modules\CustomFields;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;
use Modules\CustomFields\Models\CustomFieldDefinition;
use Modules\CustomFields\Services\CustomFieldRegistry;

/**
 * Bootstraps the CustomFields module.
 *
 * Registers routes, views, migrations, and hook views for every supported
 * entity type (members, teams, events, management functions, management tasks).
 * All hook views follow the pattern: one tab button + one tab section per entity.
 *
 * Hook priorities are set to 90 so custom fields appear last in modal tabs,
 * after module-specific tabs (e.g. YouthClubMode at priority 20).
 *
 * A View Composer is registered for the module-settings-section view so that
 * the hook view receives DB-loaded data without requiring @php blocks in Blade.
 */
class CustomFieldsServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void {}

    /** @return void */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerHooks();
        $this->registerViewComposers();
    }

    /** @return void */
    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    /** @return void */
    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'custom-fields');
    }

    /** @return void */
    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    /**
     * Registers hook views into the modal extension points of all supported entity types.
     *
     * @return void
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // Module settings admin section
        $hooks->register('admin.module-settings.sections', 'custom-fields::module-settings-section', 20);

        // Member modal
        $hooks->register('member.modal.tabs',     'custom-fields::member-cf-modal-tab',     90);
        $hooks->register('member.modal.sections', 'custom-fields::member-cf-modal-section', 90);

        // Team modal
        $hooks->register('team.modal.tabs',     'custom-fields::team-cf-modal-tab',     90);
        $hooks->register('team.modal.sections', 'custom-fields::team-cf-modal-section', 90);

        // Event modal
        $hooks->register('event.modal.tabs',     'custom-fields::event-cf-modal-tab',     90);
        $hooks->register('event.modal.sections', 'custom-fields::event-cf-modal-section', 90);

        // Management: function modal
        $hooks->register('management.function.modal.tabs',     'custom-fields::management-function-cf-modal-tab',     90);
        $hooks->register('management.function.modal.sections', 'custom-fields::management-function-cf-modal-section', 90);

        // Management: task modal
        $hooks->register('management.task.modal.tabs',     'custom-fields::management-task-cf-modal-tab',     90);
        $hooks->register('management.task.modal.sections', 'custom-fields::management-task-cf-modal-section', 90);
    }

    /**
     * Registers View Composers that supply data to hook-injected views.
     *
     * The module-settings-section view requires a list of all field definitions
     * and registry data. Using a View Composer avoids @php blocks with DB queries
     * in the Blade template itself.
     *
     * @return void
     */
    private function registerViewComposers(): void
    {
        View::composer('custom-fields::module-settings-section', function (ViewContract $view): void {
            // Guard: abort early if the table does not exist yet (e.g. during installation)
            if (! Schema::hasTable('custom_field_definitions')) {
                $view->with([
                    'cfAllDefs'   => collect(),
                    'cfGrouped'   => collect(),
                    'cfDefsJs'    => [],
                    'objectTypes' => [],
                    'fieldTypes'  => [],
                ]);
                return;
            }

            $cfAllDefs   = CustomFieldDefinition::orderBy('object_type')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();
            $objectTypes = CustomFieldRegistry::availableObjectTypes();
            $fieldTypes  = CustomFieldRegistry::fieldTypes();
            $cfGrouped   = $cfAllDefs->groupBy('object_type');

            // Prepare a flat JS-ready map (no Eloquent objects, no closures)
            $cfDefsJs = [];
            foreach ($cfAllDefs as $d) {
                $cfDefsJs[$d->id] = [
                    'id'          => $d->id,
                    'object_type' => $d->object_type,
                    'label'       => $d->label,
                    'field_type'  => $d->field_type,
                    'options_raw' => $d->optionsAsText(),
                    'placeholder' => $d->placeholder ?? '',
                    'is_required' => $d->is_required,
                    'sort_order'  => $d->sort_order,
                ];
            }

            $view->with(compact('cfAllDefs', 'objectTypes', 'fieldTypes', 'cfGrouped', 'cfDefsJs'));
        });
    }
}
