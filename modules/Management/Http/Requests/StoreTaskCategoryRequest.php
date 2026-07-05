<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Management\Models\EventTaskCategory;

/**
 * Validates the request data for creating a new global task category.
 *
 * Colour slugs use the same system as event_task_categories and Teams.
 * Colour is optional: a category may be created without a colour assigned.
 */
class StoreTaskCategoryRequest extends FormRequest
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
            'name.required' => 'Kategorienname ist erforderlich.',
            'color.in'      => 'Ungültiger Farbwert.',
        ];
    }
}
