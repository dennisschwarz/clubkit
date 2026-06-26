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
        <x-ck-button variant="secondary" type="submit">Abbrechen</x-ck-button>
    </form>
</div>

@if ($errors->any())
    <div class="ck-alert ck-alert--danger ck-mb-4">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

{{-- Zusammenfassung --}}
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

    {{-- ── Globale Team-Zuweisung ─────────────────────────────────────────────
         Kein name-Attribut → wird nicht submitted.
         Steuert per JS alle .ck-team-assign-Dropdowns auf Zeilenebene.
    ──────────────────────────────────────────────────────────────────────── --}}
    @if(!empty($teams))
    <x-ck-card class="ck-mb-4">
        <div class="ck-row ck-row--gap">
            <div class="ck-spacer">
                <p class="ck-font-weight-bold ck-mb-1">Team-Zuweisung (optional)</p>
                <p class="ck-text-muted ck-font-sm">
                    Wähle ein Team, um alle neu angelegten Mitglieder auf einmal zuzuweisen.
                    Die Zuweisung kann pro Spieler in der Tabelle angepasst oder entfernt werden.
                </p>
            </div>
            <div>
                {{-- Kein name-Attribut: wird nicht gesubmittet, steuert nur die per-Zeile-Dropdowns --}}
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

    {{-- Filter-Tabs + Aktionsleiste --}}
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
            <button type="button" class="ck-btn ck-btn--secondary ck-btn--sm"
                    onclick="importSelectAll()">Alle auswählen</button>
            <button type="button" class="ck-btn ck-btn--secondary ck-btn--sm"
                    onclick="importSelectNone()">Auswahl aufheben</button>
            <button type="button" class="ck-btn ck-btn--secondary ck-btn--sm"
                    onclick="importSelectNewAndChanged()">Neu + Geänderte</button>
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
                        $isUnch = $status === 'unchanged';
                    @endphp
                    <tr class="ck-import-row" data-status="{{ $status }}">
                        <td>
                            <input type="checkbox"
                                   name="selected[]"
                                   value="{{ $index }}"
                                   class="ck-import-check"
                                   {{ $isNew || $status === 'changed' ? 'checked' : '' }}
                                   {{ $isUnch ? 'disabled' : '' }}>
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
                            @if($isNew)
                            {{-- Per-Zeile Team-Dropdown: name="assign_team_id[{index}]" wird gesubmittet --}}
                            <select name="assign_team_id[{{ $index }}]"
                                    class="ck-field__input ck-field__input--sm ck-team-assign">
                                <option value="">— Kein Team —</option>
                                @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                            @else
                                <span class="ck-text-muted">–</span>
                            @endif
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
            Auswahl importieren
        </x-ck-button>
        <span class="ck-text-muted" id="importSelectedCount"></span>
    </div>

</form>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/import-preview.js') }}"></script>
<script>
    importUpdateCount();

    @if(!empty($teams))
    // Globaler Team-Dropdown → alle per-Zeile Dropdowns synchronisieren.
    // Kein el.style.* – nur Werte setzen.
    (function () {
        var globalSelect = document.getElementById('assignTeamGlobal');
        if (!globalSelect) return;

        globalSelect.addEventListener('change', function () {
            var teamId = this.value;
            document.querySelectorAll('.ck-team-assign').forEach(function (select) {
                select.value = teamId;
            });
        });
    }());
    @endif
</script>
@endpush
