{{--
    CSV import modal for event tasks.

    Included by: event-tasks-panel.blade.php
    Opened via:  ckModalOpen('ckImportModal')
    JS driver:   resources/js/modules/events/import.js

    Phase 1 (upload): default — file drop zone + template download link.
    Phase 2 (preview): shown by import.js after parsing — groups + submit.

    JS reads window.CK_Import.routes (injected in event-tasks-panel @push scripts).
--}}
<x-ck-modal id="ckImportModal" title="{{ __('events.import.modal_title') }}" size="lg">

    {{-- ── Phase 1: Upload ──────────────────────────────────────────────── --}}
    <div id="ck-import-upload-phase" class="ck-import-phase ck-import-phase--active">

        {{-- Drop / click zone wraps the hidden file input --}}
        <label for="ck-import-file-input"
               id="ck-import-drop-zone"
               class="ck-import-drop-zone">

            <span class="ck-import-drop-zone__icon" aria-hidden="true">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 16.5V9.75m0 0 3 3m-3-3-3 3
                             M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775
                             5.25 5.25 0 0 1 10.233-2.33
                             3 3 0 0 1 3.758 3.848
                             A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                </svg>
            </span>

            <span class="ck-import-drop-zone__text">
                {{ __('events.import.drop_or_click') }}
            </span>

            <span class="ck-import-drop-zone__hint">
                {{ __('events.import.drop_hint') }}
            </span>

            {{-- Visually hidden; click on the label triggers this --}}
            <input
                type="file"
                id="ck-import-file-input"
                class="ck-import-file-input"
                accept=".csv,.txt"
            >
        </label>

        {{-- Parse / upload error; shown by JS adding ck-import-error--visible --}}
        <p id="ck-import-upload-error" class="ck-import-error"></p>

        {{-- Template download --}}
        <p class="ck-import-template-hint">
            {{ __('events.import.template_hint') }}
            <a id="ck-import-template-link"
               href="#"
               class="ck-import-template-link"
               download>{{ __('events.import.download_template') }}</a>
        </p>

    </div>{{-- /upload phase --}}

    {{-- ── Phase 2: Preview ─────────────────────────────────────────────── --}}
    <div id="ck-import-preview-phase" class="ck-import-phase">

        {{-- Summary bar: "X gültige Tasks, Y fehlerhaft" — filled by JS --}}
        <div id="ck-import-summary" class="ck-import-summary">
            <span id="ck-import-summary-text"></span>
        </div>

        {{-- Category group cards rendered by import.js --}}
        <div id="ck-import-groups" class="ck-import-groups"></div>

        {{-- Footer: back + selected count + submit --}}
        <div class="ck-import-footer">
            <x-ck-button variant="secondary" onclick="ckImportReset()">
                {{ __('events.import.back') }}
            </x-ck-button>

            <div class="ck-import-footer__right">
                <span id="ck-import-selected-count" class="ck-import-selected-count"></span>
                <button
                    type="button"
                    id="ck-import-submit-btn"
                    class="ck-btn ck-btn--primary"
                    onclick="ckImportSubmit()"
                    disabled
                >{{ __('events.import.submit') }}</button>
            </div>
        </div>

    </div>{{-- /preview phase --}}

</x-ck-modal>
