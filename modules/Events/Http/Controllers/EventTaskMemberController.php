<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Events\Http\Requests\StoreEventTaskMemberRequest;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;

/**
 * Handles member assignments WITHOUT a time slot (Aufgaben-Tab).
 *
 * Creates and deletes event_task_member rows where time_from = null.
 * Permission is enforced at the route level via middleware('permission:events.manage').
 *
 * For time-slotted assignments (Einsatzplan-Tab), see EventSlotController.
 *
 * Note: task membership is checked via Event::hasTaskAssigned() which queries
 * event_task (Events' own table) without importing ManagementTask.
 */
class EventTaskMemberController extends Controller
{
    /**
     * Assign a member to an event task without a time slot.
     *
     * POST /events/{event}/members
     *
     * @param  StoreEventTaskMemberRequest  $request
     * @param  Event                        $event
     * @return JsonResponse
     */
    public function store(StoreEventTaskMemberRequest $request, Event $event): JsonResponse
    {
        // Guard: task must be assigned to this event first.
        // Event::hasTaskAssigned() uses DB::table('event_task') — no ManagementTask import.
        if (! $event->hasTaskAssigned($request->integer('task_id'))) {
            return response()->json(
                ['success' => false, 'message' => 'Aufgabe ist diesem Termin nicht zugewiesen.'],
                422
            );
        }

        $etm = EventTaskMember::create([
            'event_id'  => $event->id,
            'task_id'   => $request->integer('task_id'),
            'member_id' => $request->integer('member_id'),
            'time_from' => null,
            'time_to'   => null,
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $etm->id,
            'member_id' => $etm->member_id,
        ], 201);
    }

    /**
     * Remove a member assignment (no time slot).
     *
     * DELETE /events/{event}/members/{assignment}
     *
     * @param  Event            $event
     * @param  EventTaskMember  $assignment
     * @return JsonResponse
     */
    public function destroy(Event $event, EventTaskMember $assignment): JsonResponse
    {
        // Scope guard: must belong to this event and must not be a time slot
        if ($assignment->event_id !== $event->id || $assignment->hasTimeSlot()) {
            abort(404);
        }

        $assignment->delete();

        return response()->json(['success' => true]);
    }
}
