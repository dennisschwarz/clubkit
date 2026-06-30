<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates data for linking a management task to a treasury account.
 *
 * Creates a TreasuryTaskMeta record that marks the task as a contribution task.
 */
class StoreContributionRequest extends FormRequest
{
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
            'task_id'        => ['required', 'integer', 'exists:management_tasks,id', 'unique:treasury_task_meta,task_id'],
            'account_id'     => ['required', 'integer', 'exists:treasury_accounts,id'],
            'default_amount' => ['nullable', 'numeric', 'min:0.01'],
            'due_date'       => ['nullable', 'date'],
        ];
    }

    /**
     * Returns custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.unique' => 'Diese Aufgabe ist bereits einer Kasse zugewiesen.',
        ];
    }
}
