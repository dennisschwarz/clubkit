<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates data for updating an existing transaction category.
 */
class UpdateCategoryRequest extends FormRequest
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
            'name'             => ['required', 'string', 'max:100'],
            'transaction_type' => ['required', 'in:income,expense'],
            'color'            => ['nullable', 'string', 'max:20'],
        ];
    }
}
