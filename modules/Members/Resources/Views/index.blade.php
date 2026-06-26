@extends('core::admin.layout')
@section('title', 'Mitglieder')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder</h1>
        <p class="ck-page-subtitle">{{ $members->total() }} Mitglieder gesamt</p>
    </div>
    <div class="ck-row ck-row--gap">
        @ckHook('member.page.actions')
        <x-ck-button variant="primary" onclick="membersModalOpen('create')">
            + Mitglied hinzufügen
        </x-ck-button>
    </div>
</div>

{{-- Filter-Bar: Name (50%), Status, Spielberechtigung, Suchen/Zurücksetzen --}}
{{-- Layout via CSS-Grid (.ck-filter-bar): 2fr 1fr 1fr auto auto              --}}
<form method="GET" class="ck-filter-bar ck-mb-4">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Nach Name suchen…" class="ck-field__input">

    <select name="status" class="ck-field__input">
        <option value="">Alle Status</option>
        <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Aktiv</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inaktiv</option>
    </select>

    <select name="eligible" class="ck-field__input">
        <option value="">Spielberechtigung</option>
        <option value="1" {{ request('eligible') === '1' ? 'selected' : '' }}>✓ Spielberechtigt</option>
        <option value="0" {{ request('eligible') === '0' ? 'selected' : '' }}>✗ Nicht berechtigt</option>
    </select>

    <x-ck-button type="submit" variant="secondary">Suchen</x-ck-button>

    @if(request()->anyFilled(['q', 'status', 'eligible']))
        <x-ck-button :href="route('members.index')" variant="secondary">Zurücksetzen</x-ck-button>
    @endif
</form>

{{-- Pagination oben --}}
@if($members->hasPages())
<div class="ck-table__pagination ck-table__pagination--standalone ck-mb-2">{{ $members->links() }}</div>
@endif

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Geburtsdatum / Alter</th>
                <th>Geschlecht</th>
                <th>Spielberechtigt</th>
                @ckHook('member.table.header')
                <th>Status</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $member)
            <tr class="ck-table__row--expandable" data-member-id="{{ $member->id }}">
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
                    {{-- eligible_to_play ist ein Accessor → true wenn eligible_to_play_date <= heute --}}
                    @if($member->eligible_to_play)
                        <x-ck-badge color="green" :title="'ab ' . ($member->eligible_to_play_date?->format('d.m.Y') ?? '')">
                            ✓ Ja
                        </x-ck-badge>
                    @else
                        <x-ck-badge color="gray">Nein</x-ck-badge>
                    @endif
                </td>
                @ckHook('member.table.row')
                <td>
                    <x-ck-badge :color="$member->status === 'active' ? 'green' : 'gray'">
                        {{ $member->status === 'active' ? 'Aktiv' : 'Inaktiv' }}
                    </x-ck-badge>
                </td>
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="warning" size="icon"
                            title="Bearbeiten"
                            onclick="membersModalOpen('edit', {{ $member->id }})">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </x-ck-button>
                        <form method="POST" action="{{ route('members.destroy', $member) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="Mitglied löschen"
                                :confirm="'Mitglied ' . $member->last_name . ', ' . $member->first_name . ' wirklich löschen?'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @ckHook('member.table.expandrow')
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

    {{-- Pagination unten --}}
    @if($members->hasPages())
    <div class="ck-table__pagination">{{ $members->links() }}</div>
    @endif
</div>

<x-ck-modal id="memberModal" title="Mitglied" size="lg">

    <x-slot:tabs>
        <button class="ck-modal-tab ck-modal-tab--active"
                onclick="ckModalTab('memberModal', 'memberTab-stamm', this)">
            👤 Stammdaten
        </button>
        <button id="memberPhotoTabBtn" class="ck-modal-tab"
                onclick="ckModalTab('memberModal', 'memberTab-photo', this)">
            📷 Foto
        </button>
        @ckHook('member.modal.tabs')
    </x-slot:tabs>

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
                {{-- Spielberechtigt ab: Datum statt Checkbox.                    --}}
                {{-- Leer = nicht spielberechtigt. Der Accessor Member::eligible_to_play --}}
                {{-- gibt true zurück wenn eligible_to_play_date <= heute.         --}}
                <x-ck-field label="Spielberechtigt ab" name="eligible_to_play_date"
                    type="date" id="mFieldEligible"
                    hint="Leer = nicht spielberechtigt" />
            </div>
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'memberModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

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

    @ckHook('member.modal.sections')

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Members = {
        members: @json($membersJs),
        customFields: {
            definitions: @json($memberCfDefs),
            values: @json($memberCfValues),
            upsertRoute: "{{ url('custom-fields/values/member') }}"
        },
        routes: {
            store:  "{{ route('members.store') }}",
            update: "{{ url('members') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/members-modal.js') }}"></script>
@ckHook('member.page.scripts')
@endpush

@endsection
