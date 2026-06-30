<?php

declare(strict_types=1);

namespace Modules\YouthClubMode;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberRelation;
use Modules\YouthClubMode\Services\FamilyService;

/**
 * Bootstraps the YouthClubMode module.
 *
 * This module extends the Members module via the hook extension-point system.
 * The Members module has no knowledge of YouthClubMode.
 *
 * What this provider does:
 *  1. Registers hook views into members::index (modal tab, modal section, table column, scripts)
 *  2. Registers a view composer on members::index to inject pre-computed family data
 *  3. Loads routes for the relation CRUD endpoints
 */
class YouthClubModeServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void
    {
        // FamilyService is stateless – safe to share as a singleton
        $this->app->singleton(FamilyService::class);
    }

    /** @return void */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'youth-club-mode');

        $this->registerHooks();
        $this->registerViewComposer();
        $this->registerRoutes();
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────

    /**
     * Registers hook views that are injected into members::index at defined extension points.
     * Priority 20 ensures this module loads after any Core hooks (priority 10).
     *
     * @return void
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // Tab button in the member modal
        $hooks->register('member.modal.tabs', 'youth-club-mode::member-modal-tab', 20);

        // Tab content (family form) in the member modal
        $hooks->register('member.modal.sections', 'youth-club-mode::member-modal-section', 20);

        // Column header in the members table
        $hooks->register('member.table.header', 'youth-club-mode::member-table-header', 20);

        // Column cell per row ($member and $memberFamilies are automatically in scope)
        $hooks->register('member.table.row', 'youth-club-mode::member-table-row', 20);

        // JS file loaded after members-modal.js
        $hooks->register('member.page.scripts', 'youth-club-mode::member-page-scripts', 20);
    }

    // ── View Composer ─────────────────────────────────────────────────────────

    /**
     * Injects family-related data into members::index before rendering.
     *
     * Injected variables:
     *   $allMembersJs    → all members (for JS dropdown filtering)
     *   $relationsJs     → all relations (for JS eligibility checks)
     *   $memberFamilies  → pre-computed family data per paginated member (for Blade display)
     *   $membersJs       → enriched with a family{} object per member (for modal population)
     *
     * @return void
     */
    private function registerViewComposer(): void
    {
        view()->composer('members::index', function ($view): void {
            /** @var FamilyService $familyService */
            $familyService = $this->app->make(FamilyService::class);

            $data      = $view->getData();
            $membersJs = $data['membersJs'] ?? [];

            // Load all members (unpaginated) for JS dropdown filtering
            $allMembers   = Member::orderBy('last_name')->get();
            $allMembersJs = [];
            foreach ($allMembers as $m) {
                $allMembersJs[$m->id] = [
                    'id'            => $m->id,
                    'name'          => $m->last_name . ', ' . $m->first_name,
                    'gender'        => $m->gender,
                    'date_of_birth' => $m->date_of_birth?->format('Y-m-d'),
                ];
            }

            // Load all relations for JS duplicate detection ("already has a father?")
            $allRelations = MemberRelation::all();
            $relationsJs  = [];
            foreach ($allRelations as $r) {
                $relationsJs[] = [
                    'id'                  => $r->id,
                    'primary_member_id'   => $r->primary_member_id,
                    'secondary_member_id' => $r->secondary_member_id,
                    'relationship'        => $r->relationship,
                ];
            }

            // Pre-compute family data for each paginated member
            $memberFamilies = [];
            foreach (array_keys($membersJs) as $memberId) {
                $memberFamilies[$memberId] = $familyService->buildFamilyData(
                    $memberId,
                    $allRelations,
                    $allMembersJs
                );
            }

            // Enrich $membersJs with a family{} object (consumed by youth-club-mode.js modal population)
            foreach ($membersJs as $id => &$entry) {
                $entry['family'] = $memberFamilies[$id] ?? $familyService->emptyFamily();
            }
            unset($entry);

            $view->with([
                'membersJs'      => $membersJs,
                'allMembersJs'   => $allMembersJs,
                'relationsJs'    => $relationsJs,
                'memberFamilies' => $memberFamilies,
            ]);
        });
    }

    // ── Routes ────────────────────────────────────────────────────────────────

    /**
     * Loads routes directly in boot() – consistent with all other modules.
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
             ->group(__DIR__ . '/routes.php');
    }
}
