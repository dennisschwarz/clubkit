<?php

declare(strict_types=1);

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the AJAX request body for attaching a management task to an event.
 *
 * Only basic field validation is performed here.
 * Business rules (duplicate check, task-in-event guard) are enforced
 * in EventController::addTask() after this request passes.
 *
 * The exists:management_tasks,id rule creates an implicit dependency on the
 * management_tasks table, which is acceptable: this endpoint is only reachable
 * from the event detail page, which itself requires the Management module to be
 * installed and active. If Management is not installed the table will not exist
 * and the endpoint will never be shown to the user.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventTaskRequest extends FormRequest
{
    /**
     * @return bool
     */
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
            'task_id'     => ['required', 'integer', 'exists:management_tasks,id'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'deadline_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.required' => 'Bitte eine Aufgabe auswählen.',
            'task_id.exists'   => 'Die gewählte Aufgabe existiert nicht.',
        ];
    }
}
