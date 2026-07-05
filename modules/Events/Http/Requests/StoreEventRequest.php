<?php

declare(strict_types=1);

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for creating a new event.
 *
 * Only basic event fields are validated here; task/function/team assignments
 * are handled via separate AJAX endpoints after the event is created.
 *
 * Feature flags (tasks_enabled, functions_enabled, slots_enabled) are
 * normalised via prepareForValidation() so that unchecked HTML checkboxes
 * (which submit no value) are stored as false rather than being absent from
 * the validated payload.
 */
class StoreEventRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Coerce absent checkbox values to false before validation runs.
     *
     * An unchecked HTML checkbox does not submit a field at all.
     * Without this normalisation, $request->validated() would omit
     * the flag entirely and the model default (true) would persist
     * even when the user explicitly deselected the checkbox.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tasks_enabled'     => $this->boolean('tasks_enabled'),
            'functions_enabled' => $this->boolean('functions_enabled'),
            'slots_enabled'     => $this->boolean('slots_enabled'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:150'],
            'description'       => ['nullable', 'string'],
            'starts_at'         => ['required', 'date'],
            'ends_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location'          => ['nullable', 'string', 'max:200'],
            'notes'             => ['nullable', 'string'],
            // Feature flags: always present after prepareForValidation().
            'tasks_enabled'     => ['boolean'],
            'functions_enabled' => ['boolean'],
            'slots_enabled'     => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'         => 'Event title is required.',
            'starts_at.required'     => 'Start date is required.',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}