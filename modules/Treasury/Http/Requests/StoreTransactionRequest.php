<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

/**
 * Validates data for creating a new treasury transaction.
 *
 * Cross-module validation rules for event_id and task_id are applied
 * conditionally: the exists-rule is only added when the backing table
 * is present, preventing a QueryException if the optional module is not
 * installed.
 */
class StoreTransactionRequest extends FormRequest
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
        $rules = [
            'account_id'       => ['required', 'integer', 'exists:treasury_accounts,id'],
            'category_id'      => ['nullable', 'integer', 'exists:treasury_categories,id'],
            'type'             => ['required', 'in:income,expense'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'description'      => ['required', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'member_id'        => ['nullable', 'integer', 'exists:members,id'],
            // Optional-module columns: only validate existence when table is present.
            'event_id'         => ['nullable', 'integer'],
            'task_id'          => ['nullable', 'integer'],
        ];

        // Events module is optional — add the referential check only when installed.
        if (Schema::hasTable('events')) {
            $rules['event_id'][] = 'exists:events,id';
        }

        // Management module is declared in requires[], but be defensive regardless.
        if (Schema::hasTable('management_tasks')) {
            $rules['task_id'][] = 'exists:management_tasks,id';
        }

        return $rules;
    }
}
