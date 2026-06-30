<?php

declare(strict_types=1);

namespace Modules\Members\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for replacing a member's profile photo.
 *
 * Authorization is delegated to route middleware (permission:members.edit).
 * This class centralises the photo upload validation rules so that
 * the controller stays free of inline validate() calls.
 */
class UpdatePhotoRequest extends FormRequest
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
            'profile_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_image.required' => 'Bitte wähle ein Foto aus.',
            'profile_image.image'    => 'Die Datei muss ein Bild sein.',
            'profile_image.mimes'    => 'Nur JPEG und PNG werden unterstützt.',
            'profile_image.max'      => 'Das Foto darf maximal 3 MB groß sein.',
        ];
    }
}
