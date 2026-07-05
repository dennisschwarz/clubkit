<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Management\Models\EventTaskCategory;

/**
 * Validates the request data for creating an event task category.
 *
 * Categories are scoped to a single event. The event_id is taken from the
 * route parameter (not from the request body) in EventTaskCategoryController.
 *
 * Colour slugs are validated against EventTaskCategory::COLORS.
 * sort_order defaults to 0 in the migration; it is optional here.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventTaskCategoryRequest extends FormRequest
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
            'name'       => ['required', 'string', 'max:100'],
            'color'      => ['nullable', 'string', Rule::in(EventTaskCategory::COLORS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
