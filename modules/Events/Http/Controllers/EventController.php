<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Events\Http\Requests\StoreEventRequest;
use Modules\Events\Http\Requests\StoreEventTaskRequest;
use Modules\Events\Http\Requests\UpdateEventRequest;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Members\Models\Member;

/**
 * Handles event CRUD and the Event Detail Page.
 *
 * ── Architecture note ─────────────────────────────────────────────────────────
 *
 * Events has no direct PHP model dependencies on Management or Teams.
 * No `use Modules\Management\...` or `use Modules\Teams\...` in this file.
 *
 * Cross-module wiring runs through the hook system:
 *   ManagementServiceProvider → tasks panel, JS data, assignment column
 *   TeamsServiceProvider      → teams column, teams panel
 *
 * AJAX endpoints (completeTask, addTask, removeTask) use DB::table() directly
 * on the Events-owned tables event_task and event_task_member.
 * The {task} route parameter is an integer — no ManagementTask route-model binding.
 *
 * ── View data for optional modules ───────────────────────────────────────────
 *
 * All three booleans and the $teams collection use Schema::hasTable() / DB::table()
 * so no Eloquent cross-module imports are needed.
 *
 * $teamsInstalled      — bool: whether the teams table exists.
 * $managementInstalled — bool: whether the management_tasks table exists.
 * $teams               — collection of team records (DB::table) when installed;
 *                        empty collection otherwise.
 *
 * ── The two-screen pattern ────────────────────────────────────────────────────
 *
 *   index() / store()   → list with quick-create modal (basic fields only)
 *   show() / update()   → full detail page, managed via AJAX by events-detail.js
 */
class EventController extends Controller
{
    /**
     * @param  CustomFieldRepository $cfRepository
     */
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Renders the paginated event list.
     *
     * Passes three optional-module flags so the view can conditionally render
     * the Management and Teams columns without any direct Eloquent dependency.
     *
     * @return View
     */
    public function index(): View
    {
        $events = Event::with('creator')
            ->orderByDesc('starts_at')
            ->paginate(25);

        $teamsInstalled      = Schema::hasTable('teams');
        $managementInstalled = Schema::hasTable('management_tasks');

        // DB::table instead of Eloquent Team model — keeps Events decoupled from Teams.
        $teams = $teamsInstalled
            ? DB::table('teams')->orderBy('name')->get()
            : collect();

        return view('events::index', compact('events', 'teamsInstalled', 'managementInstalled', 'teams'));
    }

    /**
     * Creates a new event and redirects to its detail page.
     *
     * @param  StoreEventRequest $request
     * @return RedirectResponse
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = Event::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return redirect()->route('events.show', $event);
    }

    /**
     * Renders the event detail page.
     *
     * @param  Event $event
     * @return View
     */
    public function show(Event $event): View
    {
        $event->load('creator');

        $membersJs = [];
        foreach (Member::active()->orderBy('last_name')->get() as $m) {
            $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->full_name];
        }

        $customFields = $this->cfRepository->forObjectType('event');

        return view('events::show', compact('event', 'membersJs', 'customFields'));
    }

    /**
     * Updates the event and redirects back to the detail page.
     *
     * @param  UpdateEventRequest $request
     * @param  Event              $event
     * @return RedirectResponse
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('events.show', $event)->with('success', 'Termin gespeichert.');
    }

    /**
     * Deletes the event and redirects back to the index.
     *
     * @param  Event $event
     * @return RedirectResponse
     */
    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Termin gelöscht.');
    }

    /**
     * Marks an event-task as complete or incomplete via AJAX.
     *
     * @param  int $eventId
     * @param  int $taskId
     * @return JsonResponse
     */
    public function completeTask(int $eventId, int $taskId): JsonResponse
    {
        $pivot = DB::table('event_task')
            ->where('event_id', $eventId)
            ->where('task_id', $taskId)
            ->first();

        if (! $pivot) {
            return response()->json(['error' => 'Not found'], 404);
        }

        DB::table('event_task')
            ->where('event_id', $eventId)
            ->where('task_id', $taskId)
            ->update(['completed' => ! $pivot->completed]);

        return response()->json(['completed' => ! $pivot->completed]);
    }

    /**
     * Assigns an existing task to this event via AJAX.
     *
     * @param  StoreEventTaskRequest $request
     * @param  Event                 $event
     * @return JsonResponse
     */
    public function addTask(StoreEventTaskRequest $request, Event $event): JsonResponse
    {
        $taskId = $request->validated()['task_id'];

        if (DB::table('event_task')->where('event_id', $event->id)->where('task_id', $taskId)->exists()) {
            return response()->json(['error' => 'already_assigned'], 409);
        }

        DB::table('event_task')->insert([
            'event_id'   => $event->id,
            'task_id'    => $taskId,
            'completed'  => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Removes a task from this event via AJAX.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    public function removeTask(Event $event, int $taskId): JsonResponse
    {
        DB::table('event_task')
            ->where('event_id', $event->id)
            ->where('task_id', $taskId)
            ->delete();

        DB::table('event_task_member')
            ->where('event_id', $event->id)
            ->where('task_id', $taskId)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Assigns a member to an event-task slot via AJAX.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @return JsonResponse
     */
    public function assignMember(Event $event, int $taskId): JsonResponse
    {
        $data = request()->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to'   => ['nullable', 'date_format:H:i'],
        ]);

        $startsAt = $event->starts_at->toDateString();

        EventTaskMember::create([
            'event_id'  => $event->id,
            'task_id'   => $taskId,
            'member_id' => $data['member_id'],
            'time_from' => isset($data['time_from']) ? $startsAt . ' ' . $data['time_from'] . ':00' : null,
            'time_to'   => isset($data['time_to'])   ? $startsAt . ' ' . $data['time_to']   . ':00' : null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Removes a member from an event-task slot via AJAX.
     *
     * @param  Event $event
     * @param  int   $taskId
     * @param  int   $memberId
     * @return JsonResponse
     */
    public function removeMember(Event $event, int $taskId, int $memberId): JsonResponse
    {
        EventTaskMember::where('event_id', $event->id)
            ->where('task_id', $taskId)
            ->where('member_id', $memberId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
