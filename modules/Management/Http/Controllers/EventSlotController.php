<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\StoreEventSlotRequest;
use Modules\Management\Http\Requests\UpdateSlotConfigRequest;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskMember;

/**
 * Handles time-slotted member assignments for the Einsatzplan (staffing schedule) tab.
 *
 * Moved from the Events module to Management where all task-related tables live.
 *
 * This controller manages assignments where time_from IS NOT NULL.
 * Task-tab assignments (no time window) use EventTaskMemberController.
 *
 * time_from / time_to are sent as H:i strings (e.g. "10:00") and combined
 * with the event start date to produce full datetime values.
 *
 * Business rules enforced here (beyond request validation):
 *   - The task must belong to this event.
 *   - The task must be an event-day task: deadline_at IS NULL, or
 *     deadline_at date matches the event start date.
 *   - Duplicate: same task + member + time_from is rejected with 409.
 *     A member may hold multiple slots at different times within the same task.
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class EventSlotController extends Controller
{
    /**
     * Creates a time-slotted member assignment.
     *
     * H:i time strings are combined with the event date (starts_at) to
     * produce full Carbon datetime values stored in time_from / time_to.
     *
     * Returns 201 with the formatted times on success.
     * Returns 422 when the task has a future deadline (not an event-day task).
     * Returns 409 when the member is already assigned to the same task at the same start time.
     *
     * @param  StoreEventSlotRequest $request
     * @param  Event                 $event
     * @return JsonResponse
     */
    public function store(StoreEventSlotRequest $request, Event $event): JsonResponse
    {
        $data   = $request->validated();
        $taskId = $data['event_task_id'];

        // Verify the task belongs to this event.
        $task = EventTask::where('event_id', $event->id)->find($taskId);
        if (! $task) {
            return response()->json(['success' => false, 'message' => 'Task does not belong to this event.'], 422);
        }

        // Guard: only event-day tasks may have time slots.
        // Event-day task = deadline_at IS NULL OR deadline_at date == event start date.
        $eventDate = $event->starts_at->toDateString();
        if ($task->deadline_at !== null && $task->deadline_at->toDateString() !== $eventDate) {
            return response()->json([
                'success' => false,
                'message' => 'Only event-day tasks (no deadline or deadline on the event date) can have time slots.',
            ], 422);
        }

        // Combine event date with H:i time strings to produce full datetime values.
        // Resolved before the duplicate guard so $timeFrom can be reused in the query.
        $timeFrom = Carbon::parse($eventDate . ' ' . $data['time_from']);
        $timeTo   = Carbon::parse($eventDate . ' ' . $data['time_to']);

        // Prevent duplicate: same member + same task + same start time.
        // A member may hold slots at different times within the same task.
        if (EventTaskMember::where('event_task_id', $task->id)
            ->where('member_id', $data['member_id'])
            ->where('time_from', $timeFrom)
            ->exists()) {
            return response()->json(['error' => 'already_assigned'], 409);
        }

        $slot = EventTaskMember::create([
            'event_task_id' => $task->id,
            'member_id'     => $data['member_id'],
            'time_from'     => $timeFrom,
            'time_to'       => $timeTo,
        ]);

        return response()->json([
            'success'   => true,
            'id'        => $slot->id,
            'time_from' => $slot->time_from->format('H:i'),
            'time_to'   => $slot->time_to->format('H:i'),
        ], 201);
    }

    /**
     * Removes a time-slotted member assignment.
     *
     * Returns 404 if the assignment has no time window (use /members/{id} instead).
     *
     * @param  Event $event
     * @param  int   $slotId
     * @return JsonResponse
     */
    public function destroy(Event $event, int $slotId): JsonResponse
    {
        // Only timed assignments (time_from SET). Task-tab assignments use EventTaskMemberController.
        $slot = EventTaskMember::whereNotNull('time_from')
            ->whereHas('eventTask', fn ($q) => $q->where('event_id', $event->id))
            ->find($slotId);

        if (! $slot) {
            return response()->json(['error' => 'Slot not found or has no time window (use /members instead).'], 404);
        }

        $slot->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Returns the Einsatzplan panel HTML fragment for AJAX refresh.
     *
     * Used by slot-modal.js after the Speichern button or the close confirmation.
     * The EventSlotsPanelComposer is registered globally in ManagementServiceProvider
     * and fires automatically when the view is rendered, so no manual data injection
     * is needed beyond passing $event (which the composer reads from view data).
     *
     * The response is plain HTML (no layout). Inline <script> tags (e.g.
     * window.CK_ShiftGrid) are included and will be executed by the JS caller
     * via document.createElement('script').
     *
     * @param  Event $event
     * @return \Illuminate\Http\Response
     */
    public function panelFragment(Event $event): \Illuminate\Http\Response
    {
        $html = view('management::event-slots-panel')
            ->with('event', $event)
            ->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Saves the Einsatzplan slot configuration for an event task.
     *
     * Sets slot_start_time, slot_end_time, slot_interval_minutes, slot_capacity
     * on the EventTask row. These columns drive the grid column generation in
     * EventEinsatzplanPanelComposer.
     *
     * Returns 200 with the updated config on success.
     * Returns 422 when the task has a future deadline (not an event-day task).
     *
     * @param  UpdateSlotConfigRequest $request
     * @param  Event                   $event
     * @param  int                     $taskId
     * @return JsonResponse
     */
    public function updateConfig(UpdateSlotConfigRequest $request, Event $event, int $taskId): JsonResponse
    {
        $task = EventTask::where('event_id', $event->id)->find($taskId);

        if (! $task) {
            return response()->json(['error' => 'Task does not belong to this event.'], 422);
        }

        // Only event-day tasks may have an Einsatzplan grid configuration.
        $eventDate = $event->starts_at->toDateString();
        if ($task->deadline_at !== null && $task->deadline_at->toDateString() !== $eventDate) {
            return response()->json([
                'error' => 'Only event-day tasks (no deadline or deadline on the event date) can have a slot configuration.',
            ], 422);
        }

        $data = $request->validated();

        $task->update([
            'slot_start_time'       => $data['slot_start_time'],
            'slot_end_time'         => $data['slot_end_time'],
            'slot_interval_minutes' => $data['slot_interval_minutes'],
            'slot_capacity'         => $data['slot_capacity'],
        ]);

        return response()->json([
            'success'               => true,
            'slot_start_time'       => substr((string) $task->slot_start_time, 0, 5),
            'slot_end_time'         => substr((string) $task->slot_end_time, 0, 5),
            'slot_interval_minutes' => $task->slot_interval_minutes,
            'slot_capacity'         => $task->slot_capacity,
        ]);
    }
}