@extends('core::admin.layout')
@section('title', $team->name)

@section('content')

<div class="ck-page-header">
    <div>
        <a href="{{ route('teams.index') }}" class="ck-breadcrumb">← Teams</a>
        <h1 class="ck-page-title">{{ $team->name }}</h1>
        <p class="ck-page-subtitle">
            {{ $team->season ?? '' }}
            {{ $team->league    ? '· ' . $team->league    : '' }}
            {{ $team->age_class ? '· ' . $team->age_class : '' }}
            · {{ $team->members->count() }} Spieler
        </p>
    </div>
    @if($eligibleMembers->isNotEmpty())
    <x-ck-button variant="primary" onclick="ckModalOpen('addMemberModal')">
        + Spieler hinzufügen
    </x-ck-button>
    @endif
</div>

{{-- ── Kader-Tabelle ─────────────────────────────────────── --}}
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Spieler</th>
                <th>Alter</th>
                <th>Geschlecht</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($team->members->sortBy('pivot.squad_number') as $member)
            <tr>
                <td class="ck-text-muted ck-table__narrow">
                    {{ $member->pivot->squad_number ?? '–' }}
                </td>
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
                <td class="ck-text-muted">{{ $member->age ?? '–' }}</td>
                <td class="ck-text-muted">
                    @switch($member->gender)
                        @case('male')   Männlich @break
                        @case('female') Weiblich @break
                        @default –
                    @endswitch
                </td>
                <td>
                    <div class="ck-table__action-cell">
                        <form method="POST"
                              action="{{ route('teams.removeMember', [$team, $member]) }}"
                              class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="$member->last_name . ' aus dem Team entfernen?'">
                                Entfernen
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="ck-empty-state">
                    Noch keine Spieler im Kader.
                    @if($eligibleMembers->isNotEmpty())
                    <a href="javascript:void(0)" onclick="ckModalOpen('addMemberModal')">Jetzt hinzufügen</a>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ── Modal: Spieler hinzufügen ────────────────────────────
     BUG-FIX: ->pluck('last_name_first', 'id') schlug fehl,
     weil Member-Model keinen last_name_first Accessor hat.
     Lösung: Options-Array sauber mit @php aufbauen.
─────────────────────────────────────────────────────────── --}}
@if($eligibleMembers->isNotEmpty())
@php
    // Optionen-Array: [id => 'Nachname, Vorname'] für das Select
    $memberOptions = ['' => '– Mitglied auswählen –'];
    foreach ($eligibleMembers as $em) {
        $memberOptions[$em->id] = $em->last_name . ', ' . $em->first_name;
    }
@endphp

<x-ck-modal id="addMemberModal" title="Spieler hinzufügen" size="sm">
    <form method="POST" action="{{ route('teams.addMember', $team) }}">
        @csrf
        <div class="ck-form-grid">
            <x-ck-field label="Mitglied" name="member_id" type="select"
                :required="true" :options="$memberOptions" />
            <x-ck-field label="Trikotnummer" name="squad_number" type="number"
                placeholder="optional" hint="(1–99)" />
        </div>
        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">Hinzufügen</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'addMemberModal')">Abbrechen</x-ck-button>
        </div>
    </form>
</x-ck-modal>
@else
<x-ck-card class="ck-mt-5">
    <div class="ck-empty-state">
        Alle spielberechtigten Mitglieder sind bereits im Team.<br>
        <a href="{{ route('members.index') }}">Mitglieder verwalten →</a>
    </div>
</x-ck-card>
@endif

@endsection
