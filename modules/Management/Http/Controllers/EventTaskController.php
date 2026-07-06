<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\MoveEventTaskRequest;
use Modules\Management\Http\Requests\StoreEventTaskRequest;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\ManagementTask;

/**
 * Handles CRUD and state changes for event-specific tasks.
 *
 * All actions are AJAX-only (JSON responses). The event route parameter
 * is bound automatically via Laravel's route model binding (Event model).
 *
 * Security:
 *   - Each finder (findOrFail + where event_id) ensures the requested task
 *     actually belongs to the event in the route — prevents IDOR.
 *   - Permission is enforced at the route level (permission:events.manage).
 *
 * Activity logging is handled automatically via LogsActivity on EventTask.
 */
class EventTaskController extends Controller
{
    /**
     * Creates a new event-specific task.
     *
     * When template_id is provided without a name, the task name (and optionally
     * priority) is copied from the global ManagementTask template.
     * The event task is fully self-contained after creation — no runtime
     * dependency on the template record.
     *
     * Returns 201 with the created task data on success.
     *
     * @param  StoreEventTaskRequest $request
     * @param  Event                 $event
     * @return JsonResponse
     */
    public function store(StoreEventTaskRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();

        // When importing from a template, copy name and priority if not provided.
        if (! empty($data['template_id']) && empty($data['name'])) {
            $template = ManagementTask::find($data['template_id']);
            if (! $template) {
                return response()->json(['error' => 'Template not found.'], 404);
            }
            $data['name']     = $template->name;
            $data['priority'] = $data['priority'] ?? $template->priority;
        }

        // Verify category belongs to this event (prevents cross-event pollution).
        if (! empty($data['category_id'])) {
            $categoryBelongs = \Illuminate\Support\Facades\DB::table('event_task_categories')
                ->where('id', $data['category_id'])
                ->where('event_id', $event->id)
                ->exists();

            if (! $categoryBelongs) {
                return response()->json(['error' => 'Category does not belong to this event.'], 422);
            }
        }

        $task = EventTask::create(array_merge($data, [
            'event_id'   => $event->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'task'    => [
                'id'          => $task->id,
                'name'        => $task->name,
                'priority'    => $task->priority,
                'sort_order'  => $task->sort_order,
                'category_id' => $task->category_id,
                'completed'   => $task->completed,
                'deadline_at' => $task->deadline_at?->toDateTimeString(),
                'notes'       => $task->notes,
                'template_id' => $task->template_id,
            ],
        ], 201);
    }

    /**
     * Toggles the completed state of an event task.
     *
     * Used by the AJAX progress bar and task checkboxes in events-detail.js.
     * Returns the new completed state.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    public function complete(Event $event, int $taskId): JsonResponse
    {
        $task = EventTask::where('event_id', $event->id)->findOrFail($taskId);

        $task->update(['completed' => ! $task->completed]);

        return response()->json(['completed' => $task->completed]);
    }

    /**
     * Moves a task to a new category and/or position (drag & drop).
     *
     * Updates category_id and sort_order atomically.
     * Setting category_id to null moves the task to the "Allgemein" section.
     *
     * Validates that the target category (if provided) belongs to this event.
     *
     * @param  MoveEventTaskRequest $request
     * @param  Event                $event
     * @param  int                  $taskId
     * @return JsonResponse
     */
    public function move(MoveEventTaskRequest $request, Event $event, int $taskId): JsonResponse
    {
        $task = EventTask::where('event_id', $event->id)->findOrFail($taskId);

        $data = $request->validated();

        // Verify target category belongs to this event.
        if (! empty($data['category_id'])) {
            $categoryBelongs = \Illuminate\Support\Facades\DB::table('event_task_categories')
                ->where('id', $data['category_id'])
                ->where('event_id', $event->id)
                ->exists();

            if (! $categoryBelongs) {
                return response()->json(['error' => 'Category does not belong to this event.'], 422);
            }
        }

        $task->update([
            'category_id' => $data['category_id'] ?? null,
            'sort_order'  => $data['sort_order'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Deletes an event task and all its member assignments (cascade).
     *
     * The DB CASCADE on event_task_members.event_task_id handles
     * assignment cleanup automatically.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    /**
     * Updates an existing event task's editable fields.
     *
     * Accepts: name (required), priority, deadline_at, notes, category_id.
     * Returns the updated task on success.
     *
     * @param  Request $request
     * @param  Event   $event
     * @param  int     $taskId
     * @return JsonResponse
     */
    public function update(Request $request, Event $event, int $taskId): JsonResponse
    {
        $task = EventTask::where('event_id', $event->id)->findOrFail($taskId);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'priority'    => ['nullable', 'string', 'in:normal,important,critical'],
            'deadline_at' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $task->update([
            'name'        => $validated['name'],
            'priority'    => $validated['priority']    ?? $task->priority,
            'deadline_at' => $validated['deadline_at'] ?? null,
            'notes'       => $validated['notes']       ?? null,
            'category_id' => array_key_exists('category_id', $validated)
                ? $validated['category_id']
                : $task->category_id,
        ]);

        return response()->json(['success' => true, 'task' => $task->fresh()]);
    }

    /**
     * Deletes an event task permanently.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    public function destroy(Event $event, int $taskId): JsonResponse
    {
        $task = EventTask::where('event_id', $event->id)->findOrFail($taskId);
        $task->delete();

        return response()->json(['success' => true]);
    }
}