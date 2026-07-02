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

{{--
    Filter bar: 50% name field | 50% right side with 3 equal columns
    URL format: ?filter[q]=... | ?filter[status]=... | ?filter[eligible]=...
    Layout via CSS grid (.ck-members-filter / .ck-members-filter__right in forms.css)
--}}
<form method="GET" class="ck-members-filter ck-mb-4">
    <input type="text" name="filter[q]" value="{{ request('filter.q') }}"
           placeholder="Nach Name suchen…" class="ck-field__input">

    <div class="ck-members-filter__right">
        <select name="filter[status]" class="ck-field__input">
            <option value="">Alle Status</option>
            <option value="active"   {{ request('filter.status') === 'active'   ? 'selected' : '' }}>Aktiv</option>
            <option value="inactive" {{ request('filter.status') === 'inactive' ? 'selected' : '' }}>Inaktiv</option>
        </select>

        <select name="filter[eligible]" class="ck-field__input">
            <option value="">Spielberechtigung</option>
            <option value="1" {{ request('filter.eligible') === '1' ? 'selected' : '' }}>✓ Spielberechtigt</option>
            <option value="0" {{ request('filter.eligible') === '0' ? 'selected' : '' }}>✗ Nicht berechtigt</option>
        </select>

        <x-ck-button type="submit" variant="secondary">Suchen</x-ck-button>
    </div>
</form>

{{-- Pagination top --}}
@if($members->hasPages())
<div class="ck-table__pagination ck-table__pagination--standalone ck-mb-2">{{ $members->links() }}</div>
@endif

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <x-ck-sort-header column="last_name"  label="Name" />
                <x-ck-sort-header column="date_of_birth" label="Geburtsdatum / Alter" />
                <x-ck-sort-header column="pass_number"   label="Passnummer" />
                <x-ck-sort-header column="eligible_to_play_date" label="Spielberechtigt" />
                @ckHook('member.table.header')
                <x-ck-sort-header column="status" label="Status" />
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
                <td class="ck-text-muted">{{ $member->pass_number ?? '–' }}</td>
                <td>
                    {{-- eligible_to_play is an accessor → true when eligible_to_play_date <= today --}}
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
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                        </x-ck-button>
                        {{--
                            No inline <form>. The global #ck-delete-form in layout.blade.php
                            is used via data-delete-url.
                            → Prevents <form> as a block element in the action-cell layout.
                        --}}
                        <x-ck-button variant="danger" size="icon"
                            title="Löschen"
                            data-delete-url="{{ route('members.destroy', $member) }}"
                            :confirm="'Mitglied ' . $member->last_name . ', ' . $member->first_name . ' wirklich löschen?'">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </x-ck-button>
                    </div>
                </td>
            </tr>
            @ckHook('member.table.expandrow')
            @empty
            <tr>
                <td colspan="99" class="ck-empty-state">
                    Keine Mitglieder gefunden.
                    <x-ck-button type="button" variant="primary" size="sm" onclick="membersModalOpen('create')">
                        Jetzt hinzufügen
                    </x-ck-button>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination bottom --}}
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
                <x-ck-field label="Vorname"    name="first_name"    id="mFieldFirstName"   :required="true" />
                <x-ck-field label="Nachname"   name="last_name"     id="mFieldLastName"    :required="true" />
                <x-ck-field label="Passnummer" name="pass_number"   id="mFieldPassNumber" />
                <x-ck-field label="Geschlecht" name="gender"        id="mFieldGender"     type="select"
                    :options="['' => '– nicht angegeben –', 'male' => 'Männlich', 'female' => 'Weiblich', 'diverse' => 'Divers']" />
                <x-ck-field label="Geburtsdatum" name="date_of_birth" type="date"
                    id="mFieldDob" :max="now()->subDay()->format('Y-m-d')" />
                <x-ck-field label="Status" name="status" type="select" id="mFieldStatus"
                    :options="['active' => 'Aktiv', 'inactive' => 'Inaktiv']" />
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
                        id="mFieldPhoto" accept="image/jpeg,image/png" />
                    <p class="ck-form-hint">Erlaubte Formate: JPEG, PNG. Maximale Größe: 3 MB.</p>
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

@ckHook('member.page.scripts')

@push('scripts')
<script>
    window.CK_Members = {
        members: @json($membersJs),
        routes: {
            store:  "{{ route('members.store') }}",
            {{--
                Base URL only – members-modal.js appends '/' + memberId for PATCH
                and '/' + memberId + '/photo' for the photo form.
                Do NOT add /{id} here: that would produce /members/{id}/11.
            --}}
            update: "{{ url('members') }}",
        }
    };
</script>
@vite('resources/js/modules/members-modal.js')
@endpush

@endsection
