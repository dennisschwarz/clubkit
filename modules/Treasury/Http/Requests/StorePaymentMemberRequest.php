<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates data for adding a member to a contribution task's payment list.
 *
 * Creates a TreasuryContributionPayment row linking a member to the task
 * with an individual payment amount (which may differ from the task default).
 */
class StorePaymentMemberRequest extends FormRequest
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
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ];
    }
}
