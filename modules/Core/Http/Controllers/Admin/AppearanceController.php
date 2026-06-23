<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Setting;

/**
 * Verwaltung des Erscheinungsbilds.
 * Unterstützt normale Form-Submits UND AJAX-Requests.
 * AJAX-Response enthält die geänderten CSS-Variablen für sofortige Anwendung.
 */
class AppearanceController extends Controller
{
    /** Alle konfigurierbaren Settings mit ihren Standardwerten */
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

    /** Mapping: Setting-Key → CSS-Variable-Name */
    private const CSS_VAR_MAP = [
        'header_bg'          => '--ck-brand-bar-bg',
        'brand_bar_text'     => '--ck-brand-bar-text',
        'brand_bar_hover'    => '--ck-brand-bar-hover',
        'nav_bar_bg'         => '--ck-nav-bar-bg',
        'nav_bar_text'       => '--ck-nav-bar-text',
        'nav_bar_hover'      => '--ck-nav-bar-hover',
        'nav_bar_active_bar' => '--ck-nav-bar-active-bar',
        'nav_bar_font_size'  => '--ck-nav-bar-font-size',  // px-Suffix nötig
        'subtab_bg'          => '--ck-subtab-bg',
        'subtab_text'        => '--ck-subtab-text',
        'subtab_hover'       => '--ck-subtab-hover',
        'subtab_active_bar'  => '--ck-subtab-active-bar',
        'subtab_font_size'   => '--ck-subtab-font-size',   // px-Suffix nötig
        'body_bg'            => '--ck-bg',
    ];

    /** Erlaubte Schriftgrößen */
    private const FONT_SIZES = ['11', '12', '13', '14', '15', '16'];

    // ── View ───────────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
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

    // ── Update (Form + AJAX) ────────────────────────────────────────────────

    /**
     * Einstellungen speichern.
     *
     * Alle Felder sind `sometimes` – erlaubt pro-Sektion-AJAX-Saves.
     *
     * BUG-FIX Emoji-Checkbox:
     *   HTML-Forms senden eine unchecked Checkbox NICHT → has() = false → '0' korrekt.
     *   AJAX (FormData) sendet IMMER einen Wert ('0' oder '1') → has() = immer true.
     *   Lösung: input() direkt auf '1' prüfen, NICHT has() als Schalter nutzen.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $hexRule  = ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $fontRule = ['sometimes', 'required', 'in:' . implode(',', self::FONT_SIZES)];

        $validated = $request->validate([
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
        ]);

        // ── Checkbox-Normalisierung (BUG-FIX) ─────────────────────────────
        // Nur wenn der Emoji-Key tatsächlich in dieser Sektion gesendet wurde.
        // AJAX sendet immer '0' oder '1' explizit → input() direkt prüfen.
        // HTML-Form: unchecked → nicht gesendet → has() = false → '0' korrekt.
        if ($request->has('nav_bar_show_emojis')) {
            $validated['nav_bar_show_emojis'] = $request->input('nav_bar_show_emojis') === '1' ? '1' : '0';
        }
        if ($request->has('subtab_show_emojis')) {
            $validated['subtab_show_emojis'] = $request->input('subtab_show_emojis') === '1' ? '1' : '0';
        }

        // Logo herausnehmen – separater Upload-Pfad
        unset($validated['logo']);

        // Werte speichern
        Setting::setMany($validated);

        // Logo verarbeiten
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

        // ── AJAX-Response ──────────────────────────────────────────────────
        if ($request->ajax() || $request->wantsJson()) {

            // CSS-Variablen für die sofortige Anwendung im Browser
            $cssVars = [];
            foreach ($validated as $key => $value) {
                if (isset(self::CSS_VAR_MAP[$key])) {
                    $cssVar = self::CSS_VAR_MAP[$key];
                    $cssVars[$cssVar] = str_ends_with($key, '_font_size')
                        ? $value . 'px'
                        : $value;
                }
            }

            // Seitenreload nötig wenn Emojis, Vereinsname oder Logo geändert wurden
            $needsReload = $request->has('nav_bar_show_emojis')
                        || $request->has('subtab_show_emojis')
                        || array_key_exists('club_name', $validated)
                        || $logoChanged;

            return response()->json([
                'success'     => true,
                'cssVars'     => $cssVars,
                'needsReload' => $needsReload,
                'message'     => 'Gespeichert.',
            ]);
        }

        // ── Normaler Form-Submit ────────────────────────────────────────────
        return back()->with('success', 'Erscheinungsbild gespeichert.');
    }

    // ── Logo löschen ────────────────────────────────────────────────────────

    public function deleteLogo(): JsonResponse|RedirectResponse
    {
        $oldPath = Setting::getValue('logo_path');
        if (!empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        Setting::setValue('logo_path', '');

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'needsReload' => true, 'message' => 'Logo entfernt.']);
        }

        return back()->with('success', 'Logo entfernt.');
    }
}
