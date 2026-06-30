<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Management\Models\ManagementTask;

/**
 * Validates the request data for creating a new management task.
 *
 * category_id and priority are optional.
 * Allowed priority values are defined in ManagementTask::PRIORITIES.
 *
 * The exists:teams,id rule is conditional: only applied when Teams is installed.
 */
class StoreTaskRequest extends FormRequest
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
        $teamIdRule = array_values(array_filter([
            'integer',
            Schema::hasTable('teams') ? 'exists:teams,id' : null,
        ]));

        return [
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'category_id'  => ['nullable', 'integer', 'exists:management_task_categories,id'],
            'priority'     => ['nullable', 'string', Rule::in(ManagementTask::PRIORITIES)],
            'team_ids'     => ['nullable', 'array'],
            'team_ids.*'   => $teamIdRule,
            'member_ids'   => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'       => 'Name der Aufgabe ist erforderlich.',
            'category_id.exists'  => 'Die gewählte Kategorie existiert nicht.',
            'priority.in'         => 'Ungültige Priorität.',
            'team_ids.*.exists'   => 'Ein gewähltes Team existiert nicht.',
            'member_ids.*.exists' => 'Ein gewähltes Mitglied existiert nicht.',
        ];
    }
}
