@extends('core::admin.layout')
@section('title', 'Teams')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Teams</h1>
        <p class="ck-page-subtitle">{{ $teams->count() }} Teams gesamt</p>
    </div>
    {{-- FIX: teamsModalOpen('create') – nicht ckModalOpen() direkt.
         ckModalOpen() setzt keine action/method → PATCH an aktuelle URL --}}
    <x-ck-button variant="primary" onclick="teamsModalOpen('create')">
        + Team anlegen
    </x-ck-button>
</div>

<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Saison</th>
                <th>Liga</th>
                <th>Altersklasse</th>
                <th>Spieler</th>
                <th>Status</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($teams as $team)
            <tr>
                <td class="ck-table__bold">
                    <a href="{{ route('teams.show', $team) }}" class="ck-table__link">
                        {{ $team->name }}
                    </a>
                </td>
                <td class="ck-text-muted">{{ $team->season    ?? '–' }}</td>
                <td class="ck-text-muted">{{ $team->league    ?? '–' }}</td>
                <td class="ck-text-muted">{{ $team->age_class ?? '–' }}</td>
                <td>
                    <x-ck-badge color="blue">{{ $team->members_count }}</x-ck-badge>
                </td>
                <td>
                    <x-ck-badge :color="$team->is_active ? 'green' : 'gray'">
                        {{ $team->is_active ? 'Aktiv' : 'Inaktiv' }}
                    </x-ck-badge>
                </td>
                <td>
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="secondary" size="sm"
                            onclick="teamsModalOpen('edit', {{ $team->id }})">
                            Bearbeiten
                        </x-ck-button>
                        <form method="POST" action="{{ route('teams.destroy', $team) }}" class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="sm" type="submit"
                                :confirm="'Team »' . $team->name . '« wirklich löschen? Alle Spielerzuordnungen werden entfernt.'">
                                Löschen
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="ck-empty-state">
                    Noch keine Teams angelegt.
                    <a href="javascript:void(0)" onclick="teamsModalOpen('create')">Jetzt anlegen</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Modal: Team anlegen / bearbeiten --}}
<x-ck-modal id="teamModal" title="Team" size="md">

    <div id="teamTab-form" class="ck-modal__section ck-modal__section--active">
        <form id="teamForm" method="POST">
            @csrf
            {{-- Wird von teamsModalOpen() auf 'POST' oder 'PATCH' gesetzt --}}
            <input type="hidden" name="_method" id="teamFormMethod" value="POST">

            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Teamname"    name="name"      id="tFieldName"     :required="true" />
                <x-ck-field label="Saison"      name="season"    id="tFieldSeason"   placeholder="z.B. 2026/27"   hint="(optional)" />
                <x-ck-field label="Liga"        name="league"    id="tFieldLeague"   placeholder="z.B. Kreisliga A" hint="(optional)" />
                <x-ck-field label="Altersklasse" name="age_class" id="tFieldAgeClass" placeholder="z.B. D-Jugend"  hint="(optional)" />
            </div>

            <div class="ck-form-grid ck-mt-4">
                <x-ck-field type="checkbox" name="is_active" id="tFieldActive" :checked="true">
                    Team ist aktiv
                </x-ck-field>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">Speichern</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'teamModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>

</x-ck-modal>

@push('scripts')
<script>
    window.CK_Teams = {
        teams:  @json($teamsJs),
        routes: {
            store:  "{{ route('teams.store') }}",
            update: "{{ url('teams') }}"
        }
    };
</script>
<script src="{{ asset('js/modules/teams-modal.js') }}"></script>
@endpush

@endsection
