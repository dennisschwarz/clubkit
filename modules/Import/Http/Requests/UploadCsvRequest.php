<?php

declare(strict_types=1);

namespace Modules\Import\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the CSV file upload in step 1 of the import wizard.
 *
 * Authorization is delegated to route middleware (permission:import.execute).
 * This class is responsible only for input validation.
 */
class UploadCsvRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'Bitte eine CSV-Datei auswählen.',
            'csv_file.mimes'    => 'Nur CSV-Dateien werden unterstützt.',
            'csv_file.max'      => 'Maximale Dateigröße: 10 MB.',
        ];
    }
}
