<?php

declare(strict_types=1);

namespace Modules\Management\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * View Composer: management::event-hero-functions
 *
 * Provides: $heroFunctions → list<array{name: string, member_name: string|null}>
 */
class EventHeroFunctionsComposer
{
    public function compose(View $view): void
    {
        $data          = $view->getData();
        $event         = $data['event']         ?? null;
        $showFunctions = $data['showFunctions'] ?? false;

        if (! $event || ! $showFunctions || ! Schema::hasTable('event_management_function')) {
            $view->with('heroFunctions', []);
            return;
        }

        $functions = DB::table('event_management_function')
            ->join('management_functions',
                'management_functions.id', '=',
                'event_management_function.management_function_id')
            ->leftJoin('members',
                'members.id', '=',
                'event_management_function.member_id')
            ->where('event_management_function.event_id', '=', $event->id)
            ->select([
                'management_functions.name',
                'members.first_name',
                'members.last_name',
            ])
            ->get();

        $heroFunctions = [];
        foreach ($functions as $row) {
            $heroFunctions[] = [
                'name'        => $row->name,
                'member_name' => $row->first_name
                    ? trim($row->first_name . ' ' . $row->last_name)
                    : null,
            ];
        }

        $view->with('heroFunctions', $heroFunctions);
    }
}
