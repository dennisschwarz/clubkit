<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for updating the member assignment of an ad-hoc event function.
 */
class UpdateEventFunctionRequest extends FormRequest
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
            'member_id' => ['nullable', 'integer'],
        ];
    }
}
