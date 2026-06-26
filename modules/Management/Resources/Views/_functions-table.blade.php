{{--
    Partial: Funktionsliste innerhalb einer Gruppe (Team oder Allgemein).
    Erwartet: $groupFunctions (Collection|array von ManagementFunction)
--}}
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead>
            <tr>
                <th>Funktionsname</th>
                <th>Teams</th>
                <th>Personen</th>
                <th>Angelegt von</th>
                <th class="ck-table__actions">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupFunctions as $fn)
            <tr>
                <td class="ck-table__bold">{{ $fn->name }}</td>
                <td>
                    @forelse($fn->teams as $team)
                        <x-ck-badge color="blue">{{ $team->name }}</x-ck-badge>
                    @empty
                        <span class="ck-text-muted">–</span>
                    @endforelse
                </td>
                <td>
                    @forelse($fn->members as $member)
                        <span class="ck-member-chip">
                            {{ $member->last_name }}, {{ $member->first_name }}
                        </span>
                    @empty
                        <span class="ck-text-muted">–</span>
                    @endforelse
                </td>
                <td class="ck-text-muted">{{ $fn->creator?->name ?? '–' }}</td>
                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <x-ck-button variant="warning" size="icon"
                            title="Funktion bearbeiten"
                            onclick="mgmtModalOpen('function', 'edit', {{ $fn->id }})">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </x-ck-button>
                        <form method="POST"
                              action="{{ route('management.functions.destroy', $fn) }}"
                              class="ck-inline-form">
                            @csrf @method('DELETE')
                            <x-ck-button variant="danger" size="icon" type="submit"
                                title="Funktion löschen"
                                :confirm="'Funktion »' . $fn->name . '« löschen? Alle Team- und Personen-Zuweisungen gehen dabei verloren.'">
                                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </x-ck-button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
