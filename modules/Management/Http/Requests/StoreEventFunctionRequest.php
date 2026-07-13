<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for creating an event-scoped ad-hoc function.
 */
class StoreEventFunctionRequest extends FormRequest
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
            'name'      => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer'],
        ];
    }
}
