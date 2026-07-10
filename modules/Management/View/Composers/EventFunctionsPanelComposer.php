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
 * Provides data for the Funktionen tab (management functions with assigned members).
 *
 * Provides:
 *   $mgmtFuncItems            → array<array{function, member, member_id}>
 *   $mgmtAvailableFunctionsJs → array<id, array{id, name}>
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

        $eventRows = [];
        if (Schema::hasTable('event_management_function')) {
            foreach (DB::table('event_management_function')
                ->where('event_id', $event->id)
                ->get() as $row) {
                $eventRows[$row->management_function_id] = $row->member_id;
            }
        }

        $allFunctions = ManagementFunction::orderBy('name')->get();
        $availableJs  = [];
        foreach ($allFunctions as $fn) {
            if (! array_key_exists($fn->id, $eventRows)) {
                $availableJs[$fn->id] = ['id' => $fn->id, 'name' => $fn->name];
            }
        }

        if (empty($eventRows)) {
            $view->with(['mgmtFuncItems' => [], 'mgmtAvailableFunctionsJs' => $availableJs]);
            return;
        }

        $assignedFunctionIds = array_keys($eventRows);
        $assignedFunctions   = ManagementFunction::whereIn('id', $assignedFunctionIds)
            ->orderBy('name')
            ->get();

        $allMemberIds  = array_filter(array_values($eventRows));
        $memberRecords = [];
        if (! empty($allMemberIds) && Schema::hasTable('members')) {
            foreach (DB::table('members')
                ->whereIn('id', array_unique($allMemberIds))
                ->select('id', 'first_name', 'last_name')
                ->get() as $m) {
                $memberRecords[$m->id] = $m;
            }
        }

        $items = [];
        foreach ($assignedFunctions as $fn) {
            $memberId = $eventRows[$fn->id] ?? null;
            $items[]  = [
                'function'  => $fn,
                'member'    => $memberId ? ($memberRecords[$memberId] ?? null) : null,
                'member_id' => $memberId,
            ];
        }

        $view->with([
            'mgmtFuncItems'            => $items,
            'mgmtAvailableFunctionsJs' => $availableJs,
        ]);
    }
}
