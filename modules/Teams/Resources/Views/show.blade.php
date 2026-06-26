@extends('core::admin.layout')
@section('title', $team->name)

@section('content')

<div class="ck-page-header">
    <div>
        <a href="{{ route('teams.index') }}" class="ck-breadcrumb">← Teams</a>
        <h1 class="ck-page-title">{{ $team->name }}</h1>
        <p class="ck-page-subtitle">
            @if($team->is_competition)
                <x-ck-badge color="blue">Wettbewerb</x-ck-badge>
            @else
                <x-ck-badge color="gray">Freizeit</x-ck-badge>
            @endif
            @if($team->eligible_only)
                <x-ck-badge color="orange">Nur Spielberechtigte</x-ck-badge>
            @endif
            @if($team->season)
                <span class="ck-text-muted"> · {{ $team->season }}</span>
            @endif
            @if($team->league)
                <span class="ck-text-muted"> · {{ $team->league }}</span>
            @endif
            @if($team->age_class)
                <span class="ck-text-muted"> · {{ $team->age_class }}</span>
            @endif
            <span class="ck-text-muted"> · {{ $team->members->count() }} Mitglieder</span>
        </p>
    </div>
    <div class="ck-row">
        @ckHook('team.show.header')

        @if(!$team->is_active)
            {{-- Inaktives Team: kein Button, nur Hinweis --}}
            <div class="ck-alert ck-alert--warning">
                ⚠️ Inaktives Team – Mitglieder können nicht hinzugefügt werden.
            </div>
        @elseif($availableMembers->isNotEmpty())
            <x-ck-button variant="primary" onclick="ckModalOpen('addMemberModal')">
                + Mitglied hinzufügen
            </x-ck-button>
        @endif
    </div>
</div>

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Mitglied</th>
                <th>Alter</th>
                <th>Geschlecht</th>
                @ckHook('team.show.table.header')
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
                                 class="ck-avatar ck-avatar--sm">
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
                @ckHook('team.show.table.row')
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <form method="POST"
                              action="{{ route('teams.removeMember', [$team, $member]) }}"
                              class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="Mitglied entfernen"
                                :confirm="$member->last_name . ' aus dem Team entfernen?'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="99" class="ck-empty-state">
                    Noch keine Mitglieder im Team.
                    @if($canAddMembers && $availableMembers->isNotEmpty())
                        <a href="javascript:void(0)" onclick="ckModalOpen('addMemberModal')">Jetzt hinzufügen</a>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@ckHook('team.show.sections')

{{-- Mitglied-hinzufügen Modal: nur wenn Team aktiv und Mitglieder verfügbar --}}
@if($canAddMembers && $availableMembers->isNotEmpty())
    @php
        $memberOptions = ['' => '– Mitglied auswählen –'];
        foreach ($availableMembers as $am) {
            $memberOptions[$am->id] = $am->last_name . ', ' . $am->first_name;
        }
    @endphp

    <x-ck-modal id="addMemberModal" title="Mitglied hinzufügen" size="sm">
        <form method="POST" action="{{ route('teams.addMember', $team) }}">
            @csrf
            <div class="ck-form-grid">
                <x-ck-field label="Mitglied" name="member_id" type="select"
                    :required="true" :options="$memberOptions" />
                <x-ck-field label="Trikotnummer" name="squad_number" type="number"
                    placeholder="optional" hint="(1–99)" />
            </div>
            @if($team->eligible_only)
                <p class="ck-text-muted ck-text-sm ck-mt-2">
                    ⚠️ Dieses Team ist auf spielberechtigte Mitglieder beschränkt.
                </p>
            @endif
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Hinzufügen</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'addMemberModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </x-ck-modal>

@elseif($canAddMembers && $availableMembers->isEmpty())
    <x-ck-card class="ck-mt-5">
        <div class="ck-empty-state">
            @if($team->eligible_only)
                Alle spielberechtigten Mitglieder sind bereits im Team.
            @else
                Alle Mitglieder sind bereits im Team.
            @endif
            <br><a href="{{ route('members.index') }}">Mitglieder verwalten →</a>
        </div>
    </x-ck-card>
@endif

@endsection
