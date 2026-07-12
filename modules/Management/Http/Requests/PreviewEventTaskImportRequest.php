<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the uploaded CSV file for the import preview step.
 *
 * Accepts CSV files up to 2 MB. The 'txt' mime type is included because
 * some operating systems (Windows) report CSV files as text/plain.
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class PreviewEventTaskImportRequest extends FormRequest
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
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv.required' => 'A CSV file is required.',
            'csv.file'     => 'The upload must be a file.',
            'csv.mimes'    => 'The file must be a CSV (.csv or .txt).',
            'csv.max'      => 'The file must not exceed 2 MB.',
        ];
    }
}
