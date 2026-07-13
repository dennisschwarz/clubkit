<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\StoreEventFunctionRequest;
use Modules\Management\Http\Requests\UpdateEventFunctionRequest;
use Modules\Management\Models\EventFunction;

/**
 * CRUD for event-scoped ad-hoc functions (event_functions table).
 *
 * Routes (registered in Management routes.php under events/{event}/):
 *   POST   /event-functions                  → store
 *   PATCH  /event-functions/{eventFunctionId} → update (member_id only)
 *   DELETE /event-functions/{eventFunctionId} → destroy
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class EventFunctionController extends Controller
{
    /**
     * Returns a JSON fragment with rendered HTML for the functions panel and
     * the hero-right column — used by functions-tab.js for AJAX DOM-Swap
     * (no full page reload required).
     *
     * Response: { panel: string, hero: string }
     *   panel — HTML of management::event-functions-panel
     *   hero  — HTML of management::event-hero-functions
     *
     * Both views are driven by their registered View Composers, which read
     * $event from the view data passed below.
     *
     * @param  Event $event
     * @return JsonResponse
     */
    public function panelFragment(Event $event): JsonResponse
    {
        $panel = view('management::event-functions-panel')
            ->with('event', $event)
            ->render();

        $hero = view('management::event-hero-functions')
            ->with('event',         $event)
            ->with('showFunctions', true)
            ->render();

        return response()->json([
            'panel' => $panel,
            'hero'  => $hero,
        ]);
    }

    /**
     * Create a new ad-hoc function for the given event.
     *
     * @param  StoreEventFunctionRequest $request
     * @param  Event                     $event
     * @return JsonResponse
     */
    public function store(StoreEventFunctionRequest $request, Event $event): JsonResponse
    {
        EventFunction::create([
            'event_id'   => $event->id,
            'name'       => $request->validated('name'),
            'member_id'  => $request->validated('member_id'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update the member assignment of an existing ad-hoc function.
     *
     * Returns 404 if the function does not belong to this event.
     *
     * @param  UpdateEventFunctionRequest $request
     * @param  Event                      $event
     * @param  int                        $eventFunctionId
     * @return JsonResponse
     */
    public function update(UpdateEventFunctionRequest $request, Event $event, int $eventFunctionId): JsonResponse
    {
        $fn = EventFunction::where('event_id', $event->id)->findOrFail($eventFunctionId);
        $fn->update(['member_id' => $request->validated('member_id')]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove an ad-hoc function from an event.
     *
     * Returns 404 if the function does not belong to this event.
     *
     * @param  Event $event
     * @param  int   $eventFunctionId
     * @return JsonResponse
     */
    public function destroy(Event $event, int $eventFunctionId): JsonResponse
    {
        $fn = EventFunction::where('event_id', $event->id)->findOrFail($eventFunctionId);
        $fn->delete();

        return response()->json(['success' => true]);
    }
}