<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\Events\Http\Requests\StoreEventSlotRequest;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;

/**
 * Handles time-slotted member assignments for the Einsatzplan-Tab.
 *
 * Creates and deletes event_task_member rows where time_from + time_to are set.
 * Permission is enforced at the route level via middleware('permission:events.manage').
 *
 * The client sends H:i time strings (e.g. "09:00").
 * The controller combines them with the event date to produce full datetime values
 * as required by the event_task_member.time_from / time_to dateTime columns.
 *
 * Rules:
 *   - Only event-day tasks may receive a time slot
 *     (deadline_at IS NULL or deadline_at::date = event start date).
 *   - UNIQUE constraint (event_id, task_id, member_id) prevents double assignment.
 *
 * For assignments without a time slot (Aufgaben-Tab), see EventTaskMemberController.
 *
 * Note: task membership is checked via Event::hasEventDayTaskAssigned() which
 * queries event_task (Events' own table) without importing ManagementTask.
 */
class EventSlotController extends Controller
{
    /**
     * Assign a member to an event-day task with a time slot.
     *
     * POST /events/{event}/slots
     *
     * @param  StoreEventSlotRequest  $request
     * @param  Event                  $event
     * @return JsonResponse
     */
    public function store(StoreEventSlotRequest $request, Event $event): JsonResponse
    {
        // Guard: task must be assigned to this event and be an event-day task.
        // Event::hasEventDayTaskAssigned() uses DB::table('event_task') — no ManagementTask import.
        $isEventDayTask = $event->hasEventDayTaskAssigned(
            $request->integer('task_id'),
            $event->starts_at->toDateString()
        );

        if (! $isEventDayTask) {
            return response()->json([
                'success' => false,
                'message' => 'Nur Eventtag-Aufgaben (ohne Deadline oder Deadline = Eventtag) können Zeitslots erhalten.',
            ], 422);
        }

        // Combine the event date with the H:i time strings to produce full datetimes
        $eventDate = $event->starts_at->toDateString();
        $timeFrom  = Carbon::parse($eventDate . ' ' . $request->input('time_from') . ':00');
        $timeTo    = Carbon::parse($eventDate . ' ' . $request->input('time_to')   . ':00');

        $slot = EventTaskMember::create([
            'event_id'  => $event->id,
            'task_id'   => $request->integer('task_id'),
            'member_id' => $request->integer('member_id'),
            'time_from' => $timeFrom,
            'time_to'   => $timeTo,
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $slot->id,
            'task_id'   => $slot->task_id,
            'member_id' => $slot->member_id,
            'time_from' => $slot->time_from->format('H:i'),
            'time_to'   => $slot->time_to->format('H:i'),
        ], 201);
    }

    /**
     * Remove a time-slotted assignment.
     *
     * DELETE /events/{event}/slots/{slot}
     *
     * @param  Event            $event
     * @param  EventTaskMember  $slot
     * @return JsonResponse
     */
    public function destroy(Event $event, EventTaskMember $slot): JsonResponse
    {
        // Scope guard: must belong to this event and must be a time slot
        if ($slot->event_id !== $event->id || ! $slot->hasTimeSlot()) {
            abort(404);
        }

        $slot->delete();

        return response()->json(['success' => true]);
    }
}
