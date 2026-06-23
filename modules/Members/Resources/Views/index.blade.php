@extends('core::admin.layout')
@section('title', 'Mitglieder')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder</h1>
        <p class="ck-page-subtitle">{{ $members->total() }} Mitglieder gesamt</p>
    </div>
    <x-ck-button variant="primary" onclick="ckModalOpen('memberModal')">
        + Mitglied hinzufügen
    </x-ck-button>
</div>

<form method="GET" class="ck-row" style="margin-bottom:16px; flex-wrap:wrap; gap:10px;">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Name suchen…" class="ck-field__input" style="flex:1; min-width:200px;">
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
                <th>Geb. / Alter</th>
                <th>Geschlecht</th>
                <th>Spielberechtigt</th>
                <th>Status</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $member)
            <tr>
                <td>
                    <div class="ck-row">
                        {{-- Foto oder Avatar --}}
                        @if($member->profile_image)
                            <img src="{{ asset('storage/' . $member->profile_image) }}"
                                 alt="{{ $member->last_name }}"
                                 class="ck-avatar ck-avatar--sm ck-avatar--photo">
                        @else
                            <div class="ck-avatar ck-avatar--sm">
                                {{ strtoupper(substr($member->last_name, 0, 1)) }}
                            </div>
                        @endif
                        <span style="font-weight:600;">{{ $member->last_name }}, {{ $member->first_name }}</span>
                    </div>
                </td>
                <td class="ck-text-muted">
                    {{ $member->date_of_birth?->format('d.m.Y') ?? '–' }}
                    {{ $member->age ? '(' . $member->age . ')' : '' }}
                </td>
                <td class="ck-text-muted">
                    @switch($member->gender)
                        @case('male')    Männlich @break
                        @case('female')  Weiblich @break
                        @case('diverse') Divers   @break
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
                <td>
                    <x-ck-badge :color="$member->status === 'active' ? 'green' : 'gray'">
                        {{ $member->status === 'active' ? 'Aktiv' : 'Inaktiv' }}
                    </x-ck-badge>
                </td>
                <td>
                    <div class="ck-row" style="justify-content:flex-end; gap:6px;">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="membersModalOpen('edit', {{ $member->id }})">
                            Bearbeiten
                        </x-ck-button>
                        <form method="POST" action="{{ route('members.destroy', $member) }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="$member->last_name . ', ' . $member->first_name . ' wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="ck-text-muted" style="text-align:center; padding:40px;">
                    Keine Mitglieder gefunden.
                    <a href="javascript:void(0)" onclick="ckModalOpen('memberModal')"
                       style="color:var(--ck-accent-dark); text-decoration:none; margin-left:6px;">
                        Jetzt anlegen
                    </a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($members->hasPages())
    <div class="ck-table__pagination">{{ $members->links() }}</div>
    @endif
</div>

{{-- MODAL --}}
<x-ck-modal id="memberModal" title="Mitglied" size="lg">

    <x-slot:tabs>
        <button class="ck-modal-tab ck-modal-tab--active"
                onclick="ckModalTab('memberModal', 'memberTab-stamm', this)">
            👤 Stammdaten
        </button>
        <button class="ck-modal-tab"
                onclick="ckModalTab('memberModal', 'memberTab-photo', this)">
            📷 Foto
        </button>
    </x-slot:tabs>

    {{-- Tab 1: Stammdaten --}}
    <div id="memberTab-stamm" class="ck-modal__section ck-modal__section--active">
        <form id="memberForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="memberFormMethod" value="PATCH">

            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Vorname"    name="first_name"    id="mFieldFirstName" :required="true" />
                <x-ck-field label="Nachname"   name="last_name"     id="mFieldLastName"  :required="true" />
                <x-ck-field label="Geschlecht" name="gender"        id="mFieldGender"    type="select"
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

    {{-- Tab 2: Foto --}}
    <div id="memberTab-photo" class="ck-modal__section">
        <form id="memberPhotoForm" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="PATCH">

            <div class="ck-row" style="gap:20px; margin-bottom:20px; flex-wrap:wrap;">
                {{-- Vorschau --}}
                <div id="photoPreviewWrap">
                    <div id="photoPreviewPlaceholder"
                         class="ck-avatar ck-avatar--lg ck-field__file-preview--placeholder"
                         style="width:72px; height:72px; font-size:28px;">
                        👤
                    </div>
                    <img id="photoPreview" src="" alt="Vorschau"
                         class="ck-avatar ck-field__file-preview is-hidden"
                         style="width:72px; height:72px;">
                </div>
                <div style="flex:1;">
                    <x-ck-field label="Neues Foto hochladen"
                        name="profile_image" type="file"
                        id="mFieldPhoto"
                        accept="image/jpeg,image/jpg,image/png"
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
@endpush

@endsection