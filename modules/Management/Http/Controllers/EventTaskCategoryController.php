<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\StoreEventTaskCategoryRequest;
use Modules\Management\Http\Requests\UpdateEventTaskCategoryRequest;
use Modules\Management\Models\EventTaskCategory;

/**
 * Handles CRUD for event-specific task categories.
 *
 * All actions are AJAX-only (JSON responses). Categories are scoped to a
 * single event — each finder uses (id + event_id) to prevent IDOR.
 *
 * When a category is deleted, the DB SET NULL constraint on
 * event_tasks.category_id automatically moves all tasks to the "Allgemein"
 * section (category_id = NULL). No tasks are deleted.
 *
 * Activity logging is handled automatically via LogsActivity on EventTaskCategory.
 * Permission is enforced at the route level (permission:events.manage).
 */
class EventTaskCategoryController extends Controller
{
    /**
     * Creates a new event task category.
     *
     * Returns 201 with the created category data on success.
     *
     * @param  StoreEventTaskCategoryRequest $request
     * @param  Event                         $event
     * @return JsonResponse
     */
    public function store(StoreEventTaskCategoryRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();

        $cat = EventTaskCategory::create(array_merge($data, [
            'event_id'   => $event->id,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'success'  => true,
            'category' => [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'color'      => $cat->color,
                'sort_order' => $cat->sort_order,
            ],
        ], 201);
    }

    /**
     * Updates the name and/or colour of an event task category.
     *
     * @param  UpdateEventTaskCategoryRequest $request
     * @param  Event                          $event
     * @param  int                            $categoryId
     * @return JsonResponse
     */
    public function update(UpdateEventTaskCategoryRequest $request, Event $event, int $categoryId): JsonResponse
    {
        $cat = EventTaskCategory::where('event_id', $event->id)->findOrFail($categoryId);
        $cat->update($request->validated());

        return response()->json(['success' => true]);
    }

    /**
     * Deletes an event task category.
     *
     * Tasks in this category are NOT deleted. The DB SET NULL constraint on
     * event_tasks.category_id moves them to the "Allgemein" section automatically.
     * The moved_count in the response allows the JS to update the UI immediately.
     *
     * @param  Event $event
     * @param  int   $categoryId
     * @return JsonResponse
     */
    public function destroy(Event $event, int $categoryId): JsonResponse
    {
        $cat = EventTaskCategory::where('event_id', $event->id)->findOrFail($categoryId);

        // Count tasks being moved to "Allgemein" (for JS UI update).
        $movedCount = $cat->tasks()->count();

        $cat->delete();

        return response()->json(['success' => true, 'moved_count' => $movedCount]);
    }
}
