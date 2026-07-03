<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Management\Http\Requests\StoreTaskCategoryRequest;
use Modules\Management\Http\Requests\UpdateTaskCategoryRequest;
use Modules\Management\Models\ManagementTaskCategory;

/**
 * Handles CRUD for task categories.
 *
 * Categories are managed via the Management module settings section,
 * not on a dedicated page. All actions redirect back to module settings.
 *
 * Activity logging is handled automatically via LogsActivity on the model.
 *
 * When a category is deleted, all tasks that reference it retain their data
 * but lose their category assignment (DB ON DELETE SET NULL).
 */
class TaskCategoryController extends Controller
{
    /**
     * Creates a new task category.
     * When called via AJAX (expectsJson), returns JSON with id and name.
     *
     * @param  StoreTaskCategoryRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function store(StoreTaskCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $name = $request->validated()['name'];

        $cat = ManagementTaskCategory::create([
            'name'       => $name,
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $cat->id, 'name' => $cat->name], 201);
        }

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Kategorie „' . $name . '" angelegt.');
    }

    /**
     * Updates an existing task category name.
     *
     * @param  UpdateTaskCategoryRequest $request
     * @param  ManagementTaskCategory    $taskCategory
     * @return RedirectResponse
     */
    public function update(UpdateTaskCategoryRequest $request, ManagementTaskCategory $taskCategory): RedirectResponse
    {
        $taskCategory->update(['name' => $request->validated()['name']]);

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Kategorie „' . $taskCategory->name . '" aktualisiert.');
    }

    /**
     * Deletes a task category.
     *
     * Tasks assigned to this category are NOT deleted;
     * their category_id is set to NULL by the DB constraint.
     *
     * @param  ManagementTaskCategory $taskCategory
     * @return RedirectResponse
     */
    public function destroy(ManagementTaskCategory $taskCategory): RedirectResponse
    {
        $name = $taskCategory->name;
        $taskCategory->delete();

        return redirect()->route('admin.module-settings.index')
            ->with('success', 'Kategorie „' . $name . '" gelöscht.');
    }
}