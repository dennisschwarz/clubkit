<?php

declare(strict_types=1);

namespace Modules\YouthClubMode;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberRelation;
use Modules\YouthClubMode\Services\FamilyService;

/**
 * YouthClubMode – erweitert das Members-Modul um Familienverwaltung.
 *
 * Dieses Modul hängt sich vollständig über das Hook-System in Members ein.
 * Das Members-Modul weiß nichts von YouthClubMode.
 *
 * Was dieses Modul tut:
 *  1. Hook-Views für members::index registrieren (Tab, Sektion, Tabellenspalte, Scripts)
 *  2. View Composer: members::index mit Familie-Daten anreichern (via FamilyService)
 *  3. Eigene Routen laden (POST/DELETE /members/{id}/relations)
 */
class YouthClubModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // FamilyService als Singleton – stateless, kann wiederverwendet werden
        $this->app->singleton(FamilyService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'youth-club-mode');

        $this->registerHooks();
        $this->registerViewComposer();
        $this->registerRoutes();
    }

    // ── Hook-Views registrieren ───────────────────────────────────────────

    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // Tab-Button im Member-Modal
        $hooks->register('member.modal.tabs', 'youth-club-mode::member-modal-tab', 20);

        // Tab-Inhalt (Familie-Formular)
        $hooks->register('member.modal.sections', 'youth-club-mode::member-modal-section', 20);

        // Spalten-Header in der Mitglieder-Tabelle
        $hooks->register('member.table.header', 'youth-club-mode::member-table-header', 20);

        // Spalten-Zellen pro Zeile ($member und $memberFamilies sind automatisch im Scope)
        $hooks->register('member.table.row', 'youth-club-mode::member-table-row', 20);

        // JS-Datei laden (nach members-modal.js)
        $hooks->register('member.page.scripts', 'youth-club-mode::member-page-scripts', 20);
    }

    // ── View Composer: members::index anreichern ──────────────────────────

    /**
     * Bevor members::index gerendert wird, werden eingefügt:
     *  - $allMembersJs   → alle Mitglieder (für Dropdown-Filterung in JS)
     *  - $relationsJs    → alle Verbindungen (für JS-Filterung)
     *  - $memberFamilies → vorberechnete Familiendaten der paginierten Mitglieder (für Blade-Anzeige)
     *  - $membersJs      → mit family{}-Objekt pro Mitglied angereichert (für Modal-Befüllung)
     */
    private function registerViewComposer(): void
    {
        view()->composer('members::index', function ($view) {
            /** @var FamilyService $familyService */
            $familyService = $this->app->make(FamilyService::class);

            $data      = $view->getData();
            $membersJs = $data['membersJs'] ?? [];

            // Alle Mitglieder (unpaginiert) für Dropdown-Filterung im JS
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

            // Alle Verbindungen für JS-Filterung ("hat schon einen Vater?")
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

            // Familiendaten für die paginierten Mitglieder vorberechnen
            $memberFamilies = [];
            foreach (array_keys($membersJs) as $memberId) {
                $memberFamilies[$memberId] = $familyService->buildFamilyData(
                    $memberId,
                    $allRelations,
                    $allMembersJs
                );
            }

            // $membersJs mit family{}-Objekt anreichern (für Modal-Befüllung via JS)
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

    // ── Routen ────────────────────────────────────────────────────────────

    /**
     * Routen direkt in boot() registrieren – konsistent mit allen anderen Modulen.
     * (Kein booted()-Wrapper mehr nötig, da ModuleLoader::boot() bereits nach dem
     * App-Boot-Zyklus läuft und alle Abhängigkeiten verfügbar sind.)
     */
    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
             ->group(__DIR__ . '/routes.php');
    }
}
