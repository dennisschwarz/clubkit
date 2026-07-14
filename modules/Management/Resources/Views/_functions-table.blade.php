{{--
    Partial: function list within a group (team or general).
    Variables:
      $groupFunctions  Collection  Functions for this group.
      $fnSortRaw       string      Current sort key ('name' | '-name'). Optional.

    Plain HTML throughout — no x-ck-* components inside @foreach (Blade 13.17).
    Buttons use delegated click handlers in management-modal.js.
--}}
<div class="ck-table-wrap">
    <table class="ck-table">
        <thead data-sort-col="name" data-sort-dir="asc">
            <tr>
                <th>
                    <button type="button" class="ck-sort-link ck-task-sort-btn ck-sort-link--active" data-col="name"
                            onclick="ckTaskSortBy('name', this)">
                        {{ __('management.col.name') }}<span class="ck-sort-icon">↑</span>
                    </button>
                </th>
                <th>{{ __('management.col.teams') }}</th>
                <th>{{ __('management.col.members') }}</th>
                <th>{{ __('management.col.creator') }}</th>
                <th class="ck-table__actions">{{ __('core.col.actions') }}</th>
            </tr>
        </thead>
        <tbody class="ck-mgmt-fn-sortable" data-team-id="{{ $teamId ?? 'allgemein' }}">
            @forelse($groupFunctions as $fn)
            <tr class="ck-mgmt-real-row" data-id="{{ $fn->id }}" data-sort-name="{{ strtolower($fn->name) }}">
                <td class="ck-table__bold">{{ $fn->name }}</td>

                {{-- Teams column: only when relation was eager-loaded. --}}
                <td>
                    @if($fn->relationLoaded('teams') && $fn->teams->isNotEmpty())
                        @foreach($fn->teams as $team)
                        <span class="ck-badge ck-badge--{{ $team->color ?? 'blue' }}">{{ $team->name }}</span>
                        @endforeach
                    @else
                        <span class="ck-text-muted">–</span>
                    @endif
                </td>

                {{-- Member chips with inline × remove. --}}
                <td>
                    @if($fn->relationLoaded('members') && $fn->members->isNotEmpty())
                        @foreach($fn->members as $member)
                        <span class="ck-task-member">
                            {{ $member->last_name }}, {{ $member->first_name }}
                            <button type="button"
                                    class="ck-etm-remove-btn ck-mgmt-remove-member"
                                    data-type="function"
                                    data-parent-id="{{ $fn->id }}"
                                    data-member-id="{{ $member->id }}"
                                    aria-label="{{ __('Remove') }}">×</button>
                        </span>
                        @endforeach
                    @else
                        <span class="ck-text-muted">–</span>
                    @endif
                </td>

                <td class="ck-text-muted">{{ $fn->creator?->name ?? '–' }}</td>

                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        {{-- Edit --}}
                        <button type="button"
                                class="ck-btn ck-btn--warning ck-btn--icon"
                                data-mgmt-edit="function"
                                data-id="{{ $fn->id }}"
                                title="{{ __('Edit') }}">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </button>
                        {{-- Assign members --}}
                        <button type="button"
                                class="ck-btn ck-btn--secondary ck-btn--icon"
                                data-mgmt-assign="function"
                                data-id="{{ $fn->id }}"
                                data-name="{{ $fn->name }}"
                                title="{{ __('management.assign_members') }}">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </button>
                        {{-- Delete --}}
                        <button type="button"
                                class="ck-btn ck-btn--danger ck-btn--icon"
                                data-mgmt-delete="function"
                                data-id="{{ $fn->id }}"
                                data-ck-confirm="{{ __('management.confirm_delete_function', ['name' => $fn->name]) }}"
                                title="{{ __('Delete') }}">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="ck-empty-state">{{ __('management.functions_empty') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
