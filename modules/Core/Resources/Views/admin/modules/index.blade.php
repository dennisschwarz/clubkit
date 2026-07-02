@extends('core::admin.layout')

@section('title', 'Module')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Module</h1>
        <p class="ck-page-subtitle">Installierte und verfügbare Module verwalten.</p>
    </div>
</div>

{{-- Installed modules --}}
<div>
    <div class="ck-section-label ck-mb-3">
        Installiert ({{ count($installed) }})
    </div>

    @forelse($installed as $slug => $module)
    <div class="ck-module-card {{ $module->is_active ? 'ck-module-card--active' : 'ck-module-card--inactive' }}">
        <div class="ck-module-card__header">
            <div class="ck-module-card__info">
                <div class="ck-module-card__title-row">
                    <span class="ck-module-card__name">{{ $module->name }}</span>
                    <span class="ck-module-card__version">v{{ $module->version }}</span>
                    @if($module->is_active)
                        <x-ck-badge color="green">{{ __('Active') }}</x-ck-badge>
                    @else
                        <x-ck-badge color="gray">{{ __('Inactive') }}</x-ck-badge>
                    @endif
                    @if($slug === 'core')
                        <x-ck-badge color="blue">{{ __('Required') }}</x-ck-badge>
                    @endif
                </div>
                <div class="ck-module-card__meta">
                    Installiert: {{ \Carbon\Carbon::parse($module->installed_at)->format('d.m.Y H:i') }}
                </div>
            </div>

            @if($slug !== 'core')
            <div class="ck-module-card__actions">
                @if($module->is_active)
                <form method="POST" action="{{ route('admin.modules.deactivate', $slug) }}">
                    @csrf
                    <x-ck-button type="submit" variant="warning" size="sm">{{ __('Deactivate') }}</x-ck-button>
                </form>
                @else
                    {{-- Only allow re-activation when all required dependencies are active. --}}
                    @if(empty($depsStatus[$slug] ?? []))
                    <form method="POST" action="{{ route('admin.modules.activate', $slug) }}">
                        @csrf
                        <x-ck-button type="submit" variant="secondary" size="sm">{{ __('Activate') }}</x-ck-button>
                    </form>
                    @else
                    <div class="ck-module-card__deps-blocked">
                        <span class="ck-module-card__deps-hint">
                            Benötigt aktiv: {{ implode(', ', $depsStatus[$slug]) }}
                        </span>
                        <x-ck-button type="button" variant="secondary" size="sm" disabled>{{ __('Activate') }}</x-ck-button>
                    </div>
                    @endif
                @endif

                {{-- Opens the confirmation modal instead of browser confirm() --}}
                <x-ck-button type="button" variant="danger" size="sm"
                    onclick="ckModuleRemoveOpen('{{ $slug }}', '{{ $module->name }}', '{{ route('admin.modules.remove', $slug) }}')">
                    {{ __('Remove') }}
                </x-ck-button>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="ck-module-card__empty">
        Keine Module installiert.
    </div>
    @endforelse
</div>

{{-- Available (not yet installed) modules --}}
@php
    $notInstalled = array_filter($available, fn($slug) => !isset($installed[$slug]), ARRAY_FILTER_USE_KEY);
@endphp

@if(!empty($notInstalled))
<div class="ck-mt-5">
    <div class="ck-section-label ck-mb-3">
        Verfügbar – nicht installiert ({{ count($notInstalled) }})
    </div>

    @foreach($notInstalled as $slug => $config)
    @php $missingDeps = $depsStatus[$slug] ?? []; @endphp
    <div class="ck-module-card ck-module-card--available {{ !empty($missingDeps) ? 'ck-module-card--deps-missing' : '' }}">
        <div class="ck-module-card__header">
            <div class="ck-module-card__info">
                <div class="ck-module-card__title-row">
                    <span class="ck-module-card__name">{{ $config['name'] }}</span>
                    <span class="ck-module-card__version">v{{ $config['version'] ?? '1.0.0' }}</span>
                </div>
                @if(!empty($config['description']))
                <div class="ck-module-card__meta">{{ $config['description'] }}</div>
                @endif
                @if(!empty(array_filter($config['requires'] ?? [], fn($r) => $r !== 'core')))
                <div class="ck-module-card__requires">
                    Benötigt: {{ implode(', ', array_filter($config['requires'], fn($r) => $r !== 'core')) }}
                </div>
                @endif
                {{-- Show a clear warning when required modules are not active --}}
                @if(!empty($missingDeps))
                <div class="ck-module-card__deps-warning">
                    ⚠ Nicht installierbar: {{ implode(', ', $missingDeps) }} muss zuerst installiert und aktiv sein.
                </div>
                @endif
            </div>
            <div class="ck-module-card__actions">
                @if(empty($missingDeps))
                <form method="POST" action="{{ route('admin.modules.install', $slug) }}">
                    @csrf
                    <x-ck-button type="submit" variant="primary" size="sm">{{ __('+ Install') }}</x-ck-button>
                </form>
                @else
                {{-- Install button is disabled when required modules are missing --}}
                <x-ck-button type="button" variant="primary" size="sm" disabled>{{ __('+ Install') }}</x-ck-button>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- No module.json files found --}}
@if(empty($available))
<div class="ck-alert ck-alert--warning">
    ⚠️ Keine Module in <code>modules/</code> gefunden. Prüfe ob die Modul-Ordner mit <code>module.json</code> vorhanden sind.
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     SHARED DELETE FORM
     Action is set dynamically by ckModuleRemoveOpen().
     This form lives outside the modal so requestSubmit() triggers
     the global form-submit guard in app.js (disable buttons + loading overlay).
══════════════════════════════════════════════════════════════ --}}
<form id="ck-module-remove-form" method="POST" action="">
    @csrf
    @method('DELETE')
</form>

{{-- ══════════════════════════════════════════════════════════════
     MODULE REMOVE CONFIRM MODAL
     Uses the same visual design as the global confirm modal in layout.blade.php
     but requires custom JS (ckModuleRemoveOpen) to set the dynamic DELETE route.
     CSS: .ck-modal-content .ck-modal-content--sm (not .ck-modal .ck-modal--sm)
══════════════════════════════════════════════════════════════ --}}
<div id="ck-module-remove-modal" class="ck-modal-overlay" onclick="ckModalClose(event, 'ck-module-remove-modal')">
    <div class="ck-modal-content ck-modal-content--sm" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">🗑 Modul entfernen</h2>
            <button type="button" class="ck-modal__close" onclick="ckModalClose(null, 'ck-module-remove-modal')">&times;</button>
        </div>

        <div class="ck-modal__body">
            <p class="ck-confirm__text">
                Modul <strong id="ck-module-remove-name"></strong> wirklich entfernen?
            </p>
            <div class="ck-alert ck-alert--danger">
                ⚠️ <strong>Achtung:</strong> Alle Tabellen und Daten dieses Moduls werden
                <strong>unwiderruflich gelöscht</strong>. Dieser Vorgang kann nicht rückgängig
                gemacht werden.
            </div>
        </div>

        <div class="ck-modal__footer">
            <x-ck-button variant="danger" type="button" onclick="ckModuleRemoveConfirm()">
                {{ __('Remove permanently') }}
            </x-ck-button>
            <x-ck-button variant="secondary" type="button" onclick="ckModalClose(null, 'ck-module-remove-modal')">
                {{ __('Cancel') }}
            </x-ck-button>
        </div>

    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    /**
     * Opens the module remove confirmation modal.
     * Sets the module name in the modal text and the DELETE route on the shared form.
     *
     * @param {string} slug   - Module slug
     * @param {string} name   - Human-readable module name for the modal text
     * @param {string} action - DELETE route to set on the form
     */
    window.ckModuleRemoveOpen = function (slug, name, action) {
        const nameEl = document.getElementById('ck-module-remove-name');
        const form   = document.getElementById('ck-module-remove-form');
        if (nameEl) nameEl.textContent = name;
        if (form)   form.action        = action;
        ckModalOpen('ck-module-remove-modal');
    };

    /**
     * Submits the shared module remove form.
     * Called by the "Endgültig entfernen" button inside the modal.
     */
    window.ckModuleRemoveConfirm = function () {
        ckModalClose(null, 'ck-module-remove-modal');
        const form = document.getElementById('ck-module-remove-form');
        if (form) form.requestSubmit();
    };

}());
</script>
@endpush

@endsection
