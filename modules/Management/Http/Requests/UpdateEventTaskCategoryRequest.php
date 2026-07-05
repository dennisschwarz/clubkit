<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Management\Models\EventTaskCategory;

/**
 * Validates the request data for updating an event task category.
 *
 * Only name and colour can be updated via this request.
 * sort_order is updated exclusively via the reorder endpoint (MoveEventTaskRequest).
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class UpdateEventTaskCategoryRequest extends FormRequest
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
            'name'  => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', Rule::in(EventTaskCategory::COLORS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.max'      => 'Category name must not exceed 100 characters.',
            'color.in'      => 'Invalid colour value.',
        ];
    }
}
