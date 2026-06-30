<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates appearance settings submitted via form or AJAX.
 *
 * All fields use `sometimes` so individual sections can be saved in isolation
 * (live-preview AJAX calls only send one section at a time).
 *
 * Permission is enforced at the route level via middleware('permission:core.manage').
 */
class UpdateAppearanceRequest extends FormRequest
{
    /** Allowed font size values (stored as strings in the settings table). */
    private const FONT_SIZES = ['11', '12', '13', '14', '15', '16'];

    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $hexRule  = ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $fontRule = ['sometimes', 'required', 'in:' . implode(',', self::FONT_SIZES)];

        return [
            'club_name'           => ['sometimes', 'required', 'string', 'max:60'],
            'logo'                => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:3072'],
            'header_bg'           => $hexRule,
            'brand_bar_text'      => $hexRule,
            'brand_bar_hover'     => $hexRule,
            'nav_bar_show_emojis' => ['sometimes', 'nullable', 'in:0,1'],
            'nav_bar_bg'          => $hexRule,
            'nav_bar_text'        => $hexRule,
            'nav_bar_hover'       => $hexRule,
            'nav_bar_active_bar'  => $hexRule,
            'nav_bar_font_size'   => $fontRule,
            'subtab_show_emojis'  => ['sometimes', 'nullable', 'in:0,1'],
            'subtab_bg'           => $hexRule,
            'subtab_text'         => $hexRule,
            'subtab_hover'        => $hexRule,
            'subtab_active_bar'   => $hexRule,
            'subtab_font_size'    => $fontRule,
            'body_bg'             => $hexRule,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'club_name.required' => 'Vereinsname ist erforderlich.',
            'club_name.max'      => 'Der Vereinsname darf maximal 60 Zeichen lang sein.',
            'logo.image'         => 'Das Logo muss eine Bilddatei sein.',
            'logo.mimes'         => 'Erlaubte Formate: JPEG, PNG, WebP.',
            'logo.max'           => 'Das Logo darf maximal 3 MB groß sein.',
            '*.regex'            => 'Bitte einen gültigen Hex-Farbwert eingeben (z.B. #0a1628).',
            '*.in'               => 'Ungültiger Wert.',
        ];
    }
}
