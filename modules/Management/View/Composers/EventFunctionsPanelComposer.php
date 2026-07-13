<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Management\Models\ManagementFunction;

/**
 * View Composer: management::event-functions-panel
 *
 * Merges two function sources into a single list (Option C):
 *   1. Club functions  — rows in event_management_function (ManagementFunction, reusable)
 *   2. Ad-hoc functions — rows in event_functions (EventFunction, event-scoped)
 *
 * Provides:
 *   $mgmtFuncItems            → array<array{source, id, name, member_id, member}>
 *   $mgmtAvailableFunctionsJs → array<id, array{id, name}>  (club functions not yet assigned)
 */
class EventFunctionsPanelComposer
{
    public function compose(View $view): void
    {
        $empty = ['mgmtFuncItems' => [], 'mgmtAvailableFunctionsJs' => []];

        if (! Schema::hasTable('management_functions')) {
            $view->with($empty);
            return;
        }

        $event = $view->getData()['event']
            ?? request()->route('event')
            ?? null;

        if (! $event) {
            $view->with($empty);
            return;
        }

        // ── 1. Club functions (event_management_function) ─────────────────────

        $clubRows = [];
        if (Schema::hasTable('event_management_function')) {
            foreach (DB::table('event_management_function')
                ->where('event_id', $event->id)
                ->get() as $row) {
                $clubRows[$row->management_function_id] = $row->member_id;
            }
        }

        // Available club functions = all global functions minus already-assigned ones
        $allFunctions = ManagementFunction::orderBy('name')->get();
        $availableJs  = [];
        foreach ($allFunctions as $fn) {
            if (! array_key_exists($fn->id, $clubRows)) {
                $availableJs[$fn->id] = ['id' => $fn->id, 'name' => $fn->name];
            }
        }

        // ── 2. Ad-hoc event functions (event_functions) ───────────────────────

        $adHocRows = [];
        if (Schema::hasTable('event_functions')) {
            $adHocRows = DB::table('event_functions')
                ->where('event_id', $event->id)
                ->orderBy('name')
                ->get()
                ->all();
        }

        // ── 3. Resolve all referenced member IDs in one query ─────────────────

        $clubMemberIds  = array_values(array_filter($clubRows));
        $adHocMemberIds = array_filter(array_map(static fn ($r) => $r->member_id, $adHocRows));

        $allMemberIds  = array_unique(array_merge($clubMemberIds, $adHocMemberIds));
        $memberRecords = [];
        if (! empty($allMemberIds) && Schema::hasTable('members')) {
            foreach (DB::table('members')
                ->whereIn('id', $allMemberIds)
                ->select('id', 'first_name', 'last_name')
                ->get() as $m) {
                $memberRecords[$m->id] = $m;
            }
        }

        // ── 4. Build unified item list ─────────────────────────────────────────

        $items = [];

        // Club functions (sorted by ManagementFunction name)
        if (! empty($clubRows)) {
            $assignedFns = ManagementFunction::whereIn('id', array_keys($clubRows))
                ->orderBy('name')
                ->get();

            foreach ($assignedFns as $fn) {
                $memberId = $clubRows[$fn->id] ?? null;
                $items[]  = [
                    'source'    => 'club',
                    'id'        => $fn->id,
                    'name'      => $fn->name,
                    'member_id' => $memberId,
                    'member'    => $memberId ? ($memberRecords[$memberId] ?? null) : null,
                ];
            }
        }

        // Ad-hoc event functions (already sorted by name from DB query)
        foreach ($adHocRows as $row) {
            $items[] = [
                'source'    => 'event',
                'id'        => $row->id,
                'name'      => $row->name,
                'member_id' => $row->member_id,
                'member'    => $row->member_id ? ($memberRecords[$row->member_id] ?? null) : null,
            ];
        }

        $view->with([
            'mgmtFuncItems'            => $items,
            'mgmtAvailableFunctionsJs' => $availableJs,
        ]);
    }
}
