@extends('core::admin.layout')
@section('title', $team->name)

@section('content')

<div class="ck-page-header">
    <div>
        <div class="ck-row" style="gap:10px; margin-bottom:4px;">
            <a href="{{ route('teams.index') }}" class="ck-text-muted"
               style="text-decoration:none; font-size:var(--ck-font-md);">← Teams</a>
        </div>
        <h1 class="ck-page-title">{{ $team->name }}</h1>
        <p class="ck-page-subtitle">
            {{ $team->season ?? '' }}
            {{ $team->league   ? '· ' . $team->league   : '' }}
            {{ $team->age_class ? '· ' . $team->age_class : '' }}
            · {{ $team->members->count() }} Spieler
        </p>
    </div>
    <x-ck-button variant="primary" onclick="ckModalOpen('addMemberModal')">
        + Spieler hinzufügen
    </x-ck-button>
</div>

{{-- Kader-Tabelle --}}
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Spieler</th>
                <th>Alter</th>
                <th>Geschlecht</th>
                <th style="text-align:right;">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($team->members->sortBy('pivot.squad_number') as $member)
            <tr>
                <td class="ck-text-muted" style="width:48px;">
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
                        <span style="font-weight:600;">{{ $member->last_name }}, {{ $member->first_name }}</span>
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
                <td style="text-align:right;">
                    <form method="POST"
                          action="{{ route('teams.removeMember', [$team, $member]) }}"
                          style="display:inline;">
                        @csrf @method('DELETE')
                        <x-ck-button variant="danger" size="sm" type="submit"
                            :confirm="$member->last_name . ' aus dem Team entfernen?'">
                            Entfernen
                        </x-ck-button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="ck-text-muted" style="text-align:center; padding:40px;">
                    Noch keine Spieler im Kader.
                    <a href="javascript:void(0)" onclick="ckModalOpen('addMemberModal')"
                       style="color:var(--ck-accent-dark); text-decoration:none; margin-left:6px;">
                        Jetzt hinzufügen
                    </a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- MODAL: Spieler hinzufügen --}}
<x-ck-modal id="addMemberModal" title="Spieler hinzufügen" size="sm">
    <form method="POST" action="{{ route('teams.addMember', $team) }}">
        @csrf

        @if($eligibleMembers->isEmpty())
        <div class="ck-flash ck-flash--warning" style="border-radius:var(--ck-radius); margin-bottom:16px;">
            Alle spielberechtigten Mitglieder sind bereits im Team, oder es gibt keine spielberechtigten Mitglieder.
        </div>
        @else
        <div class="ck-form-grid">
            <x-ck-field label="Mitglied" name="member_id" type="select" :required="true"
                :options="[''] + $eligibleMembers->pluck('last_name_first', 'id')->toArray()" />
            <x-ck-field label="Trikotnummer" name="squad_number" type="number"
                placeholder="optional" hint="(1–99)" />
        </div>
        @endif

        <div class="ck-form-actions">
            @if($eligibleMembers->isNotEmpty())
            <x-ck-button type="submit" variant="primary">Hinzufügen</x-ck-button>
            @endif
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'addMemberModal')">Abbrechen</x-ck-button>
        </div>
    </form>
</x-ck-modal>

@endsection
