<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\StoreEventTaskMemberRequest;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskMember;

/**
 * Handles member assignments to event tasks WITHOUT a time window (tasks tab).
 *
 * Moved from the Events module to Management where all task-related tables live.
 *
 * This controller manages assignments where time_from IS NULL.
 * Time-slotted assignments (Einsatzplan tab, time_from SET) use EventSlotController.
 *
 * Security:
 *   - store(): verifies the task belongs to this event (IDOR prevention).
 *   - destroy(): verifies time_from IS NULL and the task belongs to this event.
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class EventTaskMemberController extends Controller
{
    /**
     * Assigns a member to an event task (no time window — tasks tab).
     *
     * Returns 409 if the member is already assigned to the task.
     * Returns 422 if the task does not belong to this event.
     *
     * @param  StoreEventTaskMemberRequest $request
     * @param  Event                       $event
     * @return JsonResponse
     */
    public function store(StoreEventTaskMemberRequest $request, Event $event): JsonResponse
    {
        $data   = $request->validated();
        $taskId = $data['event_task_id'];

        // Verify the task belongs to this event.
        $task = EventTask::where('event_id', $event->id)->find($taskId);
        if (! $task) {
            return response()->json(['error' => 'Task does not belong to this event.'], 422);
        }

        // Prevent duplicate assignment.
        if (EventTaskMember::where('event_task_id', $task->id)
            ->where('member_id', $data['member_id'])
            ->exists()) {
            return response()->json(['error' => 'already_assigned'], 409);
        }

        $assignment = EventTaskMember::create([
            'event_task_id' => $task->id,
            'member_id'     => $data['member_id'],
            'time_from'     => null,
            'time_to'       => null,
        ]);

        return response()->json([
            'success'    => true,
            'assignment' => ['id' => $assignment->id, 'member_id' => $assignment->member_id],
        ], 201);
    }

    /**
     * Removes a member assignment from an event task (no time window — tasks tab).
     *
     * Returns 404 if the assignment has a time window (use /slots/{slotId} instead).
     *
     * @param  Event $event
     * @param  int   $assignmentId
     * @return JsonResponse
     */
    public function destroy(Event $event, int $assignmentId): JsonResponse
    {
        // Only task-tab assignments (no time window). Slot assignments use EventSlotController.
        $assignment = EventTaskMember::whereNull('time_from')
            ->whereHas('eventTask', fn ($q) => $q->where('event_id', $event->id))
            ->find($assignmentId);

        if (! $assignment) {
            return response()->json(['error' => 'Assignment not found or is a timed slot (use /slots instead).'], 404);
        }

        $assignment->delete();

        return response()->json(['success' => true]);
    }
}
