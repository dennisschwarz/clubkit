<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Management\Models\EventTask;

/**
 * Validates the request data for creating an event-specific task.
 *
 * A task can be created in two ways:
 *   1. Directly: name is required, template_id is null.
 *   2. From a global template: template_id is required, name is optional
 *      (the controller copies it from the template when omitted).
 *
 * category_id scope validation (task belongs to this event's category) is
 * enforced at the controller level after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventTaskRequest extends FormRequest
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
            // Name is required when no template is provided (template supplies the name).
            'name'        => ['required_without:template_id', 'nullable', 'string', 'max:200'],
            'template_id' => ['nullable', 'integer', 'exists:management_tasks,id'],
            'category_id' => ['nullable', 'integer', 'exists:event_task_categories,id'],
            'priority'    => ['nullable', 'string', Rule::in(EventTask::PRIORITIES)],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'deadline_at' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Task name is required when not importing from a template.',
            'name.max'              => 'Task name must not exceed 200 characters.',
            'template_id.exists'    => 'The selected template does not exist.',
            'category_id.exists'    => 'The selected category does not exist.',
            'priority.in'           => 'Invalid priority value.',
        ];
    }
}
