@extends('core::admin.layout')
@section('title', 'Mitglieder')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder</h1>
        <p class="ck-page-subtitle">{{ $members->total() }} Mitglieder gesamt</p>
    </div>
    <x-ck-button variant="primary" onclick="membersModalOpen('create')">
        + Mitglied hinzufügen
    </x-ck-button>
</div>

{{-- Suchleiste --}}
<form method="GET" class="ck-row ck-mb-4">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Nach Name suchen…" class="ck-field__input ck-search-input">
    <x-ck-field name="status" type="select" :value="request('status')" :options="[
        '' => 'Alle Status', 'active' => 'Aktiv', 'inactive' => 'Inaktiv',
    ]" />
    <x-ck-button type="submit" variant="secondary">Suchen</x-ck-button>
    @if(request('q') || request('status'))
        <x-ck-button :href="route('members.index')" variant="secondary">Zurücksetzen</x-ck-button>
    @endif
</form>

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Geburtsdatum / Alter</th>
                <th>Geschlecht</th>
                <th>Spielberechtigt</th>
                {{-- Extension Point: andere Module können hier Spalten hinzufügen --}}
                @ckHook('member.table.header')
                <th>Status</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $member)
            <tr>
                <td>
                    <div class="ck-row">
                        @if($member->profile_image)
                            <img src="{{ asset('storage/' . $member->profile_image) }}"
                                 alt="{{ $member->last_name }}"
                                 class="ck-avatar ck-avatar--sm ck-avatar--photo">
                        @else
                            <div class="ck-avatar ck-avatar--sm">
                                {{ strtoupper(substr($member->last_name, 0, 1)) }}
                            </div>
                        @endif
                        <span class="ck-table__bold">{{ $member->last_name }}, {{ $member->first_name }}</span>
                    </div>
                </td>
                <td class="ck-text-muted">
                    {{ $member->date_of_birth?->format('d.m.Y') ?? '–' }}
                    {{ $member->age ? '(' . $member->age . ')' : '' }}
                </td>
                <td class="ck-text-muted">
                    @switch($member->gender)
                        @case('male')    Männlich  @break
                        @case('female')  Weiblich  @break
                        @case('diverse') Divers    @break
                        @default –
                    @endswitch
                </td>
                <td>
                    @if($member->eligible_to_play)
                        <x-ck-badge color="green">✓ Ja</x-ck-badge>
                    @else
                        <x-ck-badge color="gray">Nein</x-ck-badge>
                    @endif
                </td>
                {{-- Extension Point: $member ist hier im Scope – wird automatisch übergeben --}}
                @ckHook('member.table.row')
                <td>
                    <x-ck-badge :color="$member->status === 'active' ? 'green' : 'gray'">
                        {{ $member->status === 'active' ? 'Aktiv' : 'Inaktiv' }}
                    </x-ck-badge>
                </td>
                <td>
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="membersModalOpen('edit', {{ $member->id }})">
                            Bearbeiten
                        </x-ck-button>
                        <form method="POST" action="{{ route('members.destroy', $member) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="'Mitglied ' . $member->last_name . ', ' . $member->first_name . ' wirklich löschen?'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="99" class="ck-empty-state">
                    Keine Mitglieder gefunden.
                    <a href="javascript:void(0)" onclick="membersModalOpen('create')">Jetzt hinzufügen</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($members->hasPages())
    <div class="ck-table__pagination">{{ $members->links() }}</div>
    @endif
</div>

{{-- ════════════════════════════════════════════════════════
     MODAL: Mitglied erstellen / bearbeiten
     Das Modal enthält nur die Kern-Tabs (Details, Foto).
     Weitere Tabs werden über @ckHook von anderen Modulen ergänzt.
════════════════════════════════════════════════════════ --}}
<x-ck-modal id="memberModal" title="Mitglied" size="lg">

    <x-slot:tabs>
        <button class="ck-modal-tab ck-modal-tab--active"
                onclick="ckModalTab('memberModal', 'memberTab-stamm', this)">
            👤 Stammdaten
        </button>
        {{-- Foto-Tab: wird in create-Modus via JS deaktiviert --}}
        <button id="memberPhotoTabBtn" class="ck-modal-tab"
                onclick="ckModalTab('memberModal', 'memberTab-photo', this)">
            📷 Foto
        </button>
        {{-- Extension Point: Andere Module hängen hier ihre Tabs ein --}}
        @ckHook('member.modal.tabs')
    </x-slot:tabs>

    {{-- Tab 1: Stammdaten ───────────────────────────── --}}
    <div id="memberTab-stamm" class="ck-modal__section ck-modal__section--active">
        <form id="memberForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="memberFormMethod" value="POST">

            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Vorname"     name="first_name"    id="mFieldFirstName" :required="true" />
                <x-ck-field label="Nachname"    name="last_name"     id="mFieldLastName"  :required="true" />
                <x-ck-field label="Geschlecht"  name="gender"        id="mFieldGender"    type="select"
                    :options="['' => '– nicht angegeben –', 'male' => 'Männlich', 'female' => 'Weiblich', 'diverse' => 'Divers']" />
                <x-ck-field label="Geburtsdatum" name="date_of_birth" type="date"
                    id="mFieldDob" :max="now()->subDay()->format('Y-m-d')" />
                <x-ck-field label="Status" name="status" type="select" id="mFieldStatus"
                    :options="['active' => 'Aktiv', 'inactive' => 'Inaktiv']" />
                <x-ck-field type="checkbox" name="eligible_to_play" id="mFieldEligible">
                    Spielberechtigt
                </x-ck-field>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'memberModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

    {{-- Tab 2: Foto ─────────────────────────────────── --}}
    <div id="memberTab-photo" class="ck-modal__section">
        <div id="memberPhotoCreateHint" class="ck-flash ck-flash--warning is-hidden">
            Bitte zuerst das Mitglied speichern (Tab Stammdaten), dann ein Foto hochladen.
        </div>
        <form id="memberPhotoForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="PATCH">
            <div class="ck-row ck-mb-5">
                <div>
                    <div id="photoPreviewPlaceholder" class="ck-avatar ck-avatar--lg">👤</div>
                    <img id="photoPreview" src="" alt="Vorschau"
                         class="ck-avatar ck-avatar--lg ck-avatar--photo is-hidden">
                </div>
                <div class="ck-spacer">
                    <x-ck-field label="Neues Foto hochladen" name="profile_image" type="file"
                        id="mFieldPhoto" accept="image/jpeg,image/jpg,image/png"
                        hint="JPEG oder PNG, max. 3 MB" />
                </div>
            </div>
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Foto speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'memberModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

    {{-- Extension Point: Weitere Sections (Tabs) werden hier eingehängt.
         Alle View-Variablen ($members, $membersJs etc.) stehen automatisch zur Verfügung. --}}
    @ckHook('member.modal.sections')

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Members = {
        members: @json($membersJs),
        routes: {
            store:  "{{ route('members.store') }}",
            update: "{{ url('members') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/members-modal.js') }}"></script>
{{-- Extension Point: Andere Module laden hier ihre JS-Dateien --}}
@ckHook('member.page.scripts')
@endpush

@endsection
