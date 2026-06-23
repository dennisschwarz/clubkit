<?php

declare(strict_types=1);

namespace Modules\YouthClubMode;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberParent;

/**
 * YouthClubMode – erweitert das Members-Modul.
 *
 * Dieses Modul hängt sich vollständig über das Hook-System in Members ein.
 * Das Members-Modul weiß nichts von YouthClubMode.
 *
 * Was dieses Modul tut:
 *  1. Relationen dynamisch an Member hängen (resolveRelationUsing)
 *  2. Hook-Views für members::index registrieren
 *  3. View Composer: members::index um Guardian-Daten anreichern
 *  4. Eigene Routen laden (PATCH /members/{id}/parents)
 */
class YouthClubModeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'youth-club-mode');

        $this->registerRelations();
        $this->registerHooks();
        $this->registerViewComposer();
        $this->registerRoutes();
    }

    // ── Relationen dynamisch an Member hängen ─────────────────────────────

    /**
     * fatherLink und motherLink werden dynamisch hinzugefügt.
     * Das Member-Model bleibt unverändert.
     */
    private function registerRelations(): void
    {
        Member::resolveRelationUsing('fatherLink', function (Member $model) {
            return $model->hasOne(MemberParent::class, 'member_id')
                         ->where('relationship', 'father');
        });

        Member::resolveRelationUsing('motherLink', function (Member $model) {
            return $model->hasOne(MemberParent::class, 'member_id')
                         ->where('relationship', 'mother');
        });
    }

    // ── Hook-Views registrieren ───────────────────────────────────────────

    /**
     * Alle YouthClubMode-Views an die definierten Extension Points hängen.
     * Die Views erhalten automatisch alle Variablen des Host-Views.
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // Tab-Button im Member-Modal
        $hooks->register('member.modal.tabs', 'youth-club-mode::member-modal-tab', 20);

        // Tab-Inhalt (Guardian-Formular)
        $hooks->register('member.modal.sections', 'youth-club-mode::member-modal-section', 20);

        // Spalten-Header in der Mitglieder-Tabelle
        $hooks->register('member.table.header', 'youth-club-mode::member-table-header', 20);

        // Spalten-Zellen pro Zeile ($member ist automatisch im Scope)
        $hooks->register('member.table.row', 'youth-club-mode::member-table-row', 20);

        // JS-Datei laden (nach members-modal.js, das ckEmit bereitstellt)
        $hooks->register('member.page.scripts', 'youth-club-mode::member-page-scripts', 20);
    }

    // ── View Composer: members::index anreichern ──────────────────────────

    /**
     * Bevor members::index gerendert wird, werden:
     *  - guardian-Daten (father_id, mother_id) in $membersJs eingefügt
     *  - $parentOptions (für die Guardian-Dropdowns) hinzugefügt
     *
     * Das Members-Modul weiß davon nichts. Es sieht nur seinen normalen
     * $membersJs-Array – den dieser Composer transparent anreichert.
     */
    private function registerViewComposer(): void
    {
        view()->composer('members::index', function ($view) {
            $data      = $view->getData();
            $membersJs = $data['membersJs'] ?? [];

            if (empty($membersJs)) {
                $view->with('parentOptions', ['' => '– kein Eintrag –']);
                return;
            }

            // Alle member_parents für die aktuell paginierten Mitglieder laden
            $parentRows = MemberParent::whereIn('member_id', array_keys($membersJs))->get();

            // $membersJs mit father_id / mother_id initialisieren
            foreach ($membersJs as $id => &$entry) {
                $entry['father_id'] = null;
                $entry['mother_id'] = null;
            }
            unset($entry);

            // Guardian-Daten eintragen
            foreach ($parentRows as $row) {
                $id = $row->member_id;
                if (!isset($membersJs[$id])) {
                    continue;
                }
                if ($row->relationship === 'father') {
                    $membersJs[$id]['father_id'] = $row->parent_member_id;
                } elseif ($row->relationship === 'mother') {
                    $membersJs[$id]['mother_id'] = $row->parent_member_id;
                }
            }

            // Alle Mitglieder (unpaginiert) für die Guardian-Dropdowns
            $allMembers    = Member::orderBy('last_name')->get();
            $parentOptions = ['' => '– kein Eintrag –'];
            foreach ($allMembers as $m) {
                $parentOptions[$m->id] = $m->last_name . ', ' . $m->first_name;
            }

            // Angereicherte Daten zurück in die View schreiben
            $view->with([
                'membersJs'     => $membersJs,
                'parentOptions' => $parentOptions,
            ]);
        });
    }

    // ── Routen ────────────────────────────────────────────────────────────

    private function registerRoutes(): void
    {
        $this->app->booted(function () {
            Route::middleware(['web', 'auth'])
                 ->group(__DIR__ . '/routes.php');
        });
    }
}
