<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Core\Http\Requests\UpdateAppearanceRequest;
use Modules\Core\Models\Setting;

/**
 * Manages the application appearance settings.
 *
 * Supports both regular HTML form submits and AJAX requests from the
 * live-preview editor. AJAX responses include the changed CSS variable
 * values so the browser can apply them immediately without a page reload.
 */
class AppearanceController extends Controller
{
    /** All configurable settings with their default values. */
    private const DEFAULTS = [
        'club_name'           => 'ClubKit',
        'logo_path'           => '',
        'header_bg'           => '#0a1628',
        'brand_bar_text'      => '#ffffff',
        'brand_bar_hover'     => '#e2e8f0',
        'nav_bar_show_emojis' => '1',
        'nav_bar_bg'          => '#132238',
        'nav_bar_text'        => '#a0aec0',
        'nav_bar_hover'       => '#e2e8f0',
        'nav_bar_active_bar'  => '#60a5fa',
        'nav_bar_font_size'   => '14',
        'subtab_show_emojis'  => '1',
        'subtab_bg'           => '#ffffff',
        'subtab_text'         => '#64748b',
        'subtab_hover'        => '#1e293b',
        'subtab_active_bar'   => '#60a5fa',
        'subtab_font_size'    => '14',
        'body_bg'             => '#f0f3f8',
    ];

    /** Maps setting keys to their corresponding CSS custom property names. */
    private const CSS_VAR_MAP = [
        'header_bg'          => '--ck-brand-bar-bg',
        'brand_bar_text'     => '--ck-brand-bar-text',
        'brand_bar_hover'    => '--ck-brand-bar-hover',
        'nav_bar_bg'         => '--ck-nav-bar-bg',
        'nav_bar_text'       => '--ck-nav-bar-text',
        'nav_bar_hover'      => '--ck-nav-bar-hover',
        'nav_bar_active_bar' => '--ck-nav-bar-active-bar',
        'nav_bar_font_size'  => '--ck-nav-bar-font-size',  // needs 'px' suffix
        'subtab_bg'          => '--ck-subtab-bg',
        'subtab_text'        => '--ck-subtab-text',
        'subtab_hover'       => '--ck-subtab-hover',
        'subtab_active_bar'  => '--ck-subtab-active-bar',
        'subtab_font_size'   => '--ck-subtab-font-size',   // needs 'px' suffix
        'body_bg'            => '--ck-bg',
    ];

    /**
     * Allowed font size values passed to the view for the dropdown.
     * Validation of these values is handled by UpdateAppearanceRequest.
     */
    private const FONT_SIZES = ['11', '12', '13', '14', '15', '16'];

    // ── View ──────────────────────────────────────────────────────────────────

    /**
     * @return View
     */
    public function index(): View
    {
        $settings = self::DEFAULTS;
        $settings['club_name'] = config('app.name', 'ClubKit');

        if (Schema::hasTable('settings')) {
            $saved = Setting::allCached();
            foreach (self::DEFAULTS as $key => $default) {
                if (array_key_exists($key, $saved) && $saved[$key] !== '') {
                    $settings[$key] = $saved[$key];
                }
            }
        }

        return view('core::admin.appearance.index', [
            'settings'  => $settings,
            'fontSizes' => self::FONT_SIZES,
        ]);
    }

    // ── Update (form submit + AJAX) ────────────────────────────────────────────

    /**
     * Persists appearance settings.
     *
     * All fields use 'sometimes' so sections can be saved via individual AJAX calls.
     * Validation is fully delegated to UpdateAppearanceRequest.
     *
     * Checkbox normalisation note:
     *   HTML forms do NOT send unchecked checkboxes → has() returns false → '0' is correct.
     *   AJAX (FormData) always sends an explicit value ('0' or '1') → has() is always true.
     *   Solution: check input() directly against '1', do NOT use has() as the toggle.
     *
     * @param  UpdateAppearanceRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function update(UpdateAppearanceRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Normalise emoji checkboxes (see docblock above)
        if ($request->has('nav_bar_show_emojis')) {
            $validated['nav_bar_show_emojis'] = $request->input('nav_bar_show_emojis') === '1' ? '1' : '0';
        }
        if ($request->has('subtab_show_emojis')) {
            $validated['subtab_show_emojis'] = $request->input('subtab_show_emojis') === '1' ? '1' : '0';
        }

        // Exclude logo – handled separately below
        unset($validated['logo']);

        Setting::setMany($validated);

        // Handle logo upload
        $logoChanged = false;
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $oldPath = Setting::getValue('logo_path');
            if (!empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $newPath = $request->file('logo')->store('logos', 'public');
            Setting::setValue('logo_path', $newPath);
            $logoChanged = true;
        }

        // ── AJAX response ─────────────────────────────────────────────────────
        if ($request->ajax() || $request->wantsJson()) {

            // Build the CSS variable map for immediate browser application
            $cssVars = [];
            foreach ($validated as $key => $value) {
                if (isset(self::CSS_VAR_MAP[$key])) {
                    $cssVar = self::CSS_VAR_MAP[$key];
                    $cssVars[$cssVar] = str_ends_with($key, '_font_size')
                        ? $value . 'px'
                        : $value;
                }
            }

            // A page reload is required when emojis, club name, or logo change
            $needsReload = $request->has('nav_bar_show_emojis')
                        || $request->has('subtab_show_emojis')
                        || array_key_exists('club_name', $validated)
                        || $logoChanged;

            return response()->json([
                'success'     => true,
                'cssVars'     => $cssVars,
                'needsReload' => $needsReload,
                'message'     => __('appearance.flash.saved_json'),
            ]);
        }

        // ── Standard form submit ──────────────────────────────────────────────
        return back()->with('success', __('appearance.flash.saved'));
    }

    // ── Logo deletion ─────────────────────────────────────────────────────────

    /**
     * Deletes the stored logo file and clears the logo_path setting.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function deleteLogo(): JsonResponse|RedirectResponse
    {
        $oldPath = Setting::getValue('logo_path');
        if (!empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        Setting::setValue('logo_path', '');

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'needsReload' => true, 'message' => __('appearance.flash.logo_removed')]);
        }

        return back()->with('success', __('appearance.flash.logo_removed'));
    }
}
