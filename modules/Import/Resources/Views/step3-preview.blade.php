@extends('core::admin.layout')
@section('title', 'Import-Vorschau')

@section('content')
<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder importieren</h1>
        <p class="ck-page-subtitle">Schritt 3 von 3 – Vorschau und Bestätigung</p>
    </div>
    <form method="POST" action="{{ route('import.cancel', $session->id) }}">
        @csrf
        <x-ck-button variant="secondary" type="submit">{{ __('Cancel') }}</x-ck-button>
    </form>
</div>

@if ($errors->any())
    <div class="ck-alert ck-alert--danger ck-mb-4">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

{{-- Summary --}}
<div class="ck-import-summary ck-mb-4">
    <span class="ck-import-summary__total">{{ $counts['total'] }} Datensätze</span>
    <span class="ck-import-summary__sep">·</span>
    <span class="ck-import-summary__new">✦ {{ $counts['new'] }} neu</span>
    <span class="ck-import-summary__sep">·</span>
    <span class="ck-import-summary__changed">⟳ {{ $counts['changed'] }} geändert</span>
    <span class="ck-import-summary__sep">·</span>
    <span class="ck-import-summary__unchanged">✓ {{ $counts['unchanged'] }} unverändert</span>
</div>

<form method="POST" action="{{ route('import.execute', $session->id) }}" id="importForm">
    @csrf

    {{-- ── Global team assignment ─────────────────────────────────────────────
         No name attribute → not submitted.
         Controls all .ck-team-assign dropdowns per row via JS.
    ──────────────────────────────────────────────────────────────────────── --}}
    @if(!empty($teams))
    <x-ck-card class="ck-mb-4">
        <div class="ck-row ck-row--gap">
            <div class="ck-spacer">
                <p class="ck-font-weight-bold ck-mb-1">Team-Zuweisung (optional)</p>
                <p class="ck-text-muted ck-font-sm">
                    Wähle ein Team, um alle ausgewählten Mitglieder auf einmal zuzuweisen.
                    Die Zuweisung kann pro Spieler in der Tabelle angepasst oder entfernt werden.
                </p>
            </div>
            <div>
                {{-- No name attribute: not submitted, controls the per-row dropdowns via JS --}}
                <select id="assignTeamGlobal" class="ck-field__input">
                    <option value="">— Kein Team (alle) —</option>
                    @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-ck-card>
    @endif

    {{-- Filter tabs + action bar --}}
    <div class="ck-import-toolbar ck-mb-3">
        <div class="ck-local-tabs" id="importFilterTabs">
            <button type="button" class="ck-local-tab ck-local-tab--active"
                    onclick="importFilter('all', this)">Alle ({{ $counts['total'] }})</button>
            <button type="button" class="ck-local-tab"
                    onclick="importFilter('new', this)">Neu ({{ $counts['new'] }})</button>
            <button type="button" class="ck-local-tab"
                    onclick="importFilter('changed', this)">Geändert ({{ $counts['changed'] }})</button>
            <button type="button" class="ck-local-tab"
                    onclick="importFilter('unchanged', this)">Unverändert ({{ $counts['unchanged'] }})</button>
        </div>
        <div class="ck-row ck-row--gap">
            <x-ck-button type="button" variant="secondary" size="sm"
                         onclick="importSelectAll()">{{ __('Select all') }}</x-ck-button>
            <x-ck-button type="button" variant="secondary" size="sm"
                         onclick="importSelectNone()">{{ __('Deselect all') }}</x-ck-button>
            <x-ck-button type="button" variant="secondary" size="sm"
                         onclick="importSelectNewAndChanged()">{{ __('New + Changed') }}</x-ck-button>
        </div>
    </div>

    <x-ck-card>
        <div class="ck-table-wrap">
            <table class="ck-table" id="importTable">
                <thead>
                    <tr>
                        <th class="ck-table__col--xs">
                            <input type="checkbox" id="checkAll" onchange="importToggleAll(this)">
                        </th>
                        <th class="ck-table__col--sm">Status</th>
                        <th>Name</th>
                        <th>Geburtsdatum</th>
                        <th>Passnummer</th>
                        @if(!empty($teams))
                        <th class="ck-table__col--md">Team</th>
                        @endif
                        <th>Änderungen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                    @php
                        $m      = $row['mapped'];
                        $status = $row['status'];
                        $diff   = $row['diff'] ?? [];
                        $isNew  = $status === 'new';
                    @endphp
                    <tr class="ck-import-row" data-status="{{ $status }}">
                        <td>
                            <input type="checkbox"
                                   name="selected[]"
                                   value="{{ $index }}"
                                   class="ck-import-check"
                                   {{ $isNew || $status === 'changed' ? 'checked' : '' }}>
                        </td>
                        <td>
                            @if ($isNew)
                                <x-ck-badge color="green">NEU</x-ck-badge>
                            @elseif ($status === 'changed')
                                <x-ck-badge color="orange">GEÄNDERT</x-ck-badge>
                            @else
                                <x-ck-badge color="gray">GLEICH</x-ck-badge>
                            @endif
                        </td>
                        <td>{{ $m['last_name'] }}, {{ $m['first_name'] }}</td>
                        <td>{{ isset($m['date_of_birth']) ? \Carbon\Carbon::parse($m['date_of_birth'])->format('d.m.Y') : '–' }}</td>
                        <td>{{ $m['pass_number'] ?? '–' }}</td>
                        @if(!empty($teams))
                        <td>
                            {{-- Per-row team dropdown for all rows: name="assign_team_id[{index}]" --}}
                            <select name="assign_team_id[{{ $index }}]"
                                    class="ck-field__input ck-field__input--sm ck-team-assign">
                                <option value="">— Kein Team —</option>
                                @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        @endif
                        <td>
                            @if ($status === 'changed' && count($diff))
                                <ul class="ck-import-diff">
                                    @foreach ($diff as $field => $change)
                                    <li>
                                        <span class="ck-import-diff__field">{{ $field }}:</span>
                                        <span class="ck-import-diff__old">{{ $change['old'] ?? '–' }}</span>
                                        →
                                        <span class="ck-import-diff__new">{{ $change['new'] ?? '–' }}</span>
                                    </li>
                                    @endforeach
                                </ul>
                            @else
                                –
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ck-card>

    <div class="ck-form-actions ck-mt-4">
        <x-ck-button variant="primary" type="submit" id="importSubmitBtn">
            {{ __('Import selection') }}
        </x-ck-button>
        <span class="ck-text-muted" id="importSelectedCount"></span>
    </div>

</form>
@endsection

@push('scripts')
@vite('resources/js/modules/import-preview.js')
<script>
    {{-- importUpdateCount() is already called inside DOMContentLoaded in import-preview.js.
         Calling it here synchronously would fail because @vite loads scripts as type="module"
         (deferred), so the function is not yet defined when this inline script executes. --}}

    @if(!empty($teams))
    // Global team dropdown → synchronises all per-row dropdowns.
    // No el.style.* – value assignment only.
    (function () {
        const globalSelect = document.getElementById('assignTeamGlobal');
        if (!globalSelect) return;

        globalSelect.addEventListener('change', function () {
            const teamId = this.value;
            document.querySelectorAll('.ck-team-assign').forEach(function (select) {
                select.value = teamId;
            });
        });
    }());
    @endif
</script>
@endpush