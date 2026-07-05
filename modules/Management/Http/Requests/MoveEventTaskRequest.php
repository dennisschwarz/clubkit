<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for drag & drop task reordering.
 *
 * Sent by events-detail.js when the user drops a task into a new position.
 * Updates category_id (new section) and sort_order (position within section).
 *
 * category_id is nullable: moving a task to the "Allgemein" section
 * sets category_id to null.
 *
 * Category scope validation (category belongs to this event) is enforced
 * at the controller level after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class MoveEventTaskRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:event_task_categories,id'],
            'sort_order'  => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort_order.required' => 'Sort order is required.',
            'sort_order.integer'  => 'Sort order must be an integer.',
            'category_id.exists'  => 'The target category does not exist.',
        ];
    }
}
