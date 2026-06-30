<?php

declare(strict_types=1);

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for assigning a member to an event-day task
 * with a specific time slot (Einsatzplan-Tab modal).
 *
 * Business rules beyond basic validation (event-day task check,
 * time overlap detection) are enforced in EventSlotController::store()
 * after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventSlotRequest extends FormRequest
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
            'task_id'   => ['required', 'integer', 'exists:management_tasks,id'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to'   => ['required', 'date_format:H:i', 'after:time_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.required'      => 'Bitte eine Aufgabe auswählen.',
            'task_id.exists'        => 'Die gewählte Aufgabe existiert nicht.',
            'member_id.required'    => 'Bitte ein Mitglied auswählen.',
            'member_id.exists'      => 'Das gewählte Mitglied existiert nicht.',
            'time_from.required'    => 'Startzeit ist erforderlich.',
            'time_from.date_format' => 'Startzeit muss im Format HH:MM angegeben werden.',
            'time_to.required'      => 'Endzeit ist erforderlich.',
            'time_to.date_format'   => 'Endzeit muss im Format HH:MM angegeben werden.',
            'time_to.after'         => 'Endzeit muss nach der Startzeit liegen.',
        ];
    }
}
