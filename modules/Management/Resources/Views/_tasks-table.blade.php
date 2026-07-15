{{--
    Partial: task list within a group (team or general).
    Variables:
      $groupTasks  Collection  Tasks for this group.

    Plain HTML throughout — no x-ck-* inside @foreach (Blade 13.17).
    Buttons use delegated click handlers in management-modal.js.
--}}
@php
    $priorityColors = ['high' => 'red', 'normal' => 'blue', 'low' => 'gray'];
    $priorityOrder  = ['high' => 1, 'normal' => 2, 'low' => 3];
    $isCategoryMode = isset($categoryId);
@endphp
<div class="ck-table-wrap">
    <table class="ck-table ck-table--fixed ck-mgmt-task-table">
        <colgroup>
            <col class="ck-mgmt-col--name">
            <col class="ck-mgmt-col--priority">
            <col class="ck-mgmt-col--members">
            <col class="ck-mgmt-col--creator">
            <col class="ck-mgmt-col--actions">
        </colgroup>
        <thead data-sort-col="name" data-sort-dir="asc">
            <tr>
                <th>
                    <button type="button" class="ck-sort-link ck-task-sort-btn ck-sort-link--active" data-col="name"
                            onclick="ckTaskSortBy('name', this)">
                        {{ __('management.col.name') }}<span class="ck-sort-icon">↑</span>
                    </button>
                </th>
                <th>
                    <button type="button" class="ck-sort-link ck-task-sort-btn" data-col="priority"
                            onclick="ckTaskSortBy('priority', this)">
                        {{ __('management.col.priority') }}<span class="ck-sort-icon">⇅</span>
                    </button>
                </th>
                <th>{{ __('management.col.members') }}</th>
                @if($isCategoryMode)
                <th>{{ __('management.col.teams') }}</th>
                @else
                <th>{{ __('management.col.category') }}</th>
                @endif
                <th class="ck-table__actions">{{ __('core.col.actions') }}</th>
            </tr>
        </thead>
        <tbody class="ck-mgmt-task-sortable"
            @if(isset($categoryId)) data-category-id="{{ $categoryId ?? 'allgemein' }}"
            @else data-team-id="{{ $teamId ?? 'allgemein' }}"
            @endif>
            @forelse($groupTasks as $task)
            @php
                $priColor = $priorityColors[$task->priority] ?? 'gray';
                $priOrder = $priorityOrder[$task->priority]  ?? 2;
            @endphp
            <tr class="ck-mgmt-real-row" data-id="{{ $task->id }}" data-sort-name="{{ strtolower($task->name) }}" data-sort-priority="{{ $priOrder }}">
                <td class="ck-table__bold">
                    {{ $task->name }}
                    @if($task->description)
                    <div class="ck-text-muted ck-text-sm">{{ Str::limit($task->description, 80) }}</div>
                    @endif
                </td>

                <td><span class="ck-badge ck-badge--{{ $priColor }}">{{ __('management.priority.' . $task->priority) }}</span></td>

                {{-- Member chips with inline × remove. --}}
                <td>
                    @if($task->relationLoaded('members') && $task->members->isNotEmpty())
                        @foreach($task->members as $member)
                        <span class="ck-task-member">
                            {{ $member->last_name }}, {{ $member->first_name }}
                            <button type="button"
                                    class="ck-etm-remove-btn ck-mgmt-remove-member"
                                    data-type="task"
                                    data-parent-id="{{ $task->id }}"
                                    data-member-id="{{ $member->id }}"
                                    aria-label="{{ __('Remove') }}">×</button>
                        </span>
                        @endforeach
                    @else
                        <span class="ck-text-muted">–</span>
                    @endif
                </td>

                @if($isCategoryMode)
                <td>
                    @if($task->relationLoaded('teams') && $task->teams->isNotEmpty())
                        @foreach($task->teams as $ckTeam)
                        <span class="ck-badge ck-badge--{{ $ckTeam->color ?? 'blue' }}">{{ $ckTeam->name }}</span>
                        @endforeach
                    @else
                        <span class="ck-muted">–</span>
                    @endif
                </td>
                @else
                <td>
                    @if($task->relationLoaded('category') && $task->category)
                        <span class="ck-badge ck-badge--{{ $task->category->color ?? 'gray' }}">{{ $task->category->name }}</span>
                    @else
                        <span class="ck-muted">–</span>
                    @endif
                </td>
                @endif

                <td class="ck-table__actions">
                    <div class="ck-table__action-cell">
                        <button type="button"
                                class="ck-btn ck-btn--warning ck-btn--icon"
                                data-mgmt-edit="task"
                                data-id="{{ $task->id }}"
                                title="{{ __('Edit') }}">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-8 8a2 2 0 01-.9.52l-3 .75a.5.5 0 01-.607-.606l.75-3a2 2 0 01.52-.9l8-8z"/>
                            </svg>
                        </button>
                        <button type="button"
                                class="ck-btn ck-btn--secondary ck-btn--icon"
                                data-mgmt-assign="task"
                                data-id="{{ $task->id }}"
                                data-name="{{ $task->name }}"
                                title="{{ __('management.assign_members') }}">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </button>
                        <button type="button"
                                class="ck-btn ck-btn--danger ck-btn--icon"
                                data-mgmt-delete="task"
                                data-id="{{ $task->id }}"
                                data-ck-confirm="{{ __('management.confirm_delete_task', ['name' => $task->name]) }}"
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
                <td colspan="5" class="ck-empty-state">–</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
