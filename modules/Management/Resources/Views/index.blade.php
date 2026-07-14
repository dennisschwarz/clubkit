@extends('core::admin.layout')
@section('title', __('management.title'))

@section('content')

{{-- $chevronSvg kommt vom Controller (ManagementController::chevronSvg()) --}}
{{-- und wird via @ckHook automatisch in alle Hook-Views weitergegeben.     --}}

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('management.title') }}</h1>
        <p class="ck-page-subtitle">{{ __('management.subtitle') }}</p>
    </div>
</div>

{{-- ── Local sub-tabs ──────────────────────────────────────────────────── --}}
<div class="ck-local-tabs ck-mb-5">
    <button class="ck-local-tab ck-local-tab--purple {{ request('tab') !== 'aufgaben' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('mgmtTab-funktionen', this)">
        {{ __('management.tab_functions') }}
    </button>
    <button class="ck-local-tab ck-local-tab--blue {{ request('tab') === 'aufgaben' ? 'ck-local-tab--active' : '' }}"
            onclick="ckLocalTab('mgmtTab-aufgaben', this)">
        {{ __('management.tab_tasks') }}
    </button>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     TAB: Functions
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTab-funktionen"
     class="ck-local-section {{ request('tab') !== 'aufgaben' ? 'ck-local-section--active' : '' }}">

    <div id="mgmtFunctionList">
        @if($functions->isEmpty())
        <p class="ck-empty-state">{{ __('management.functions_empty') }}
            <button type="button" class="ck-btn ck-btn--success ck-btn--sm"
                    onclick="mgmtModalOpen('function', 'create')">{{ __('core.create_now') }}</button>
        </p>
        @elseif(app('ck.hooks')->has('management.function.list'))
            @ckHook('management.function.list')
        @else
            @include('management::_functions-table', ['groupFunctions' => $functions, 'fnSortRaw' => $fnSortRaw])
        @endif
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     TAB: Tasks
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTab-aufgaben"
     class="ck-local-section {{ request('tab') === 'aufgaben' ? 'ck-local-section--active' : '' }}">

    <div id="mgmtTaskList">
        @if($tasks->isEmpty())
        <p class="ck-empty-state">{{ __('management.tasks_empty') }}
            <button type="button" class="ck-btn ck-btn--success ck-btn--sm"
                    onclick="mgmtModalOpen('task', 'create')">{{ __('core.create_now') }}</button>
        </p>
        @elseif(app('ck.hooks')->has('management.task.list'))
            @ckHook('management.task.list')
        @else
            @include('management::_tasks-table', ['groupTasks' => $tasks, 'taskSortRaw' => $taskSortRaw])
        @endif
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Create / edit function
     Raw HTML instead of <x-ck-modal> + <x-slot:tabs> – works around the PHP 8.4 Blade slot bug.
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtFunctionModal"
     class="ck-modal-overlay"
     onclick="ckModalClose(event, 'mgmtFunctionModal')">
    <div class="ck-modal-content ck-modal-content--md" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">{{ __('management.function_modal_title') }}</h2>
            <button type="button" class="ck-modal__close"
                    onclick="ckModalClose(null, 'mgmtFunctionModal')">&times;</button>
        </div>

        <div class="ck-modal__tabbar">
            <button class="ck-modal-tab ck-modal-tab--active"
                    onclick="ckModalTab('mgmtFunctionModal', 'mgmtFunctionTab-form', this)">
                {{ __('management.function_tab') }}
            </button>
            @ckHook('management.function.modal.tabs')
        </div>

        <div class="ck-modal__body">

            <div id="mgmtFunctionTab-form" class="ck-modal__section ck-modal__section--active">
                <form id="mgmtFunctionForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="mgmtFunctionFormMethod" value="POST">
                    <input type="hidden" name="team_id" id="mgmtFunctionTeamId" value="">
                    <x-ck-field :label="__('management.field.function_name')" name="name" id="mgmtFunctionFieldName" :required="true"
                                placeholder="z.B. Trainer, Co-Trainer, Betreuer, Kassenwart" />

                    <x-ck-field type="textarea" :label="__('management.field.description')" name="description"
                                id="mgmtFunctionFieldDesc" rows="3" />

                    <div class="ck-form-actions">
                        <button type="submit" class="ck-btn ck-btn--primary">{{ __('Save') }}</button>
                        <button type="button" class="ck-btn ck-btn--secondary"
                                onclick="ckModalClose(null, 'mgmtFunctionModal')">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>

            @ckHook('management.function.modal.sections')

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Create / edit task
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtTaskModal"
     class="ck-modal-overlay"
     onclick="ckModalClose(event, 'mgmtTaskModal')">
    <div class="ck-modal-content ck-modal-content--md" onclick="event.stopPropagation()">

        <div class="ck-modal__header">
            <h2 class="ck-modal__title">{{ __('management.task_modal_title') }}</h2>
            <button type="button" class="ck-modal__close"
                    onclick="ckModalClose(null, 'mgmtTaskModal')">&times;</button>
        </div>

        <div class="ck-modal__tabbar">
            <button class="ck-modal-tab ck-modal-tab--active"
                    onclick="ckModalTab('mgmtTaskModal', 'mgmtTaskTab-form', this)">
                {{ __('management.task_tab') }}
            </button>
            @ckHook('management.task.modal.tabs')
        </div>

        <div class="ck-modal__body">

            <div id="mgmtTaskTab-form" class="ck-modal__section ck-modal__section--active">
                <form id="mgmtTaskForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="mgmtTaskFormMethod" value="POST">
                    <input type="hidden" name="team_id" id="mgmtTaskTeamId" value="">
                    <x-ck-field :label="__('management.field.task_name')" name="name" id="mgmtTaskFieldName" :required="true"
                                placeholder="z.B. Platzpflege, Materialwart, Schriftführer" />
                    <x-ck-field type="textarea" :label="__('management.field.description')" name="description" id="mgmtTaskFieldDesc"
                                placeholder="Optionale Beschreibung der Aufgabe" />

                    <div class="ck-form-actions">
                        <button type="submit" class="ck-btn ck-btn--primary">{{ __('Save') }}</button>
                        <button type="button" class="ck-btn ck-btn--secondary"
                                onclick="ckModalClose(null, 'mgmtTaskModal')">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>

            @ckHook('management.task.modal.sections')

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODAL: Member assignment (SortableJS drag & drop — reuses ck-assign-* CSS)
     Populated and controlled by openMgmtAssign() in management-modal.js.
══════════════════════════════════════════════════════════════════════ --}}
<div id="mgmtAssignModal" class="ck-modal-overlay" onclick="ckModalClose(event, 'mgmtAssignModal')">
    <div class="ck-modal-content ck-modal-content--lg" onclick="event.stopPropagation()">
        <div class="ck-modal__header">
            <h2 class="ck-modal__title" id="mgmtAssignModalTitle">{{ __('management.assign_members') }}</h2>
            <button type="button" class="ck-modal__close" onclick="ckModalClose(null, 'mgmtAssignModal')">&times;</button>
        </div>
        <div class="ck-modal__body">
            <div class="ck-dual-listbox">
                <div class="ck-dual-listbox__col">
                    <span class="ck-dual-listbox__label">{{ __('management.available_members') }}</span>
                    <ul id="mgmtAssignAvailList" class="ck-assign-list"></ul>
                </div>
                <div class="ck-dual-listbox__col">
                    <span class="ck-dual-listbox__label">{{ __('management.assigned_members') }}</span>
                    <ul id="mgmtAssignedList" class="ck-assign-list ck-assign-list--assigned"></ul>
                </div>
            </div>
            <div class="ck-form-actions">
                <button type="button" class="ck-btn ck-btn--primary" id="mgmtAssignDoneBtn">{{ __('Done') }}</button>
                <button type="button" class="ck-btn ck-btn--secondary"
                        onclick="ckModalClose(null, 'mgmtAssignModal')">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.CK_Management = {
        functions: @json($functionsJs),
        tasks:     @json($tasksJs),
        members:   @json($membersJs),
        csrf:      "{{ csrf_token() }}",
        customFieldsFunction: {
            definitions: @json($mgmtFunctionCfDefs),
            values:      @json($mgmtFunctionCfValues),
            upsertRoute: "{{ url('custom-fields/values/management_function') }}"
        },
        customFieldsTask: {
            definitions: @json($mgmtTaskCfDefs),
            values:      @json($mgmtTaskCfValues),
            upsertRoute: "{{ url('custom-fields/values/management_task') }}"
        },
        routes: {
            functionStore:       "{{ route('management.functions.store') }}",
            functionUpdate:      "{{ url('management/functions') }}",
            functionDelete:      "{{ url('management/functions') }}",
            functionMemberBase:  "{{ url('management/functions') }}",
            functionMove:        "{{ url('management/functions') }}",
            taskStore:           "{{ route('management.tasks.store') }}",
            taskUpdate:          "{{ url('management/tasks') }}",
            taskDelete:          "{{ url('management/tasks') }}",
            taskMemberBase:      "{{ url('management/tasks') }}",
            taskMove:            "{{ url('management/tasks') }}",
            functionsFragment:   "{{ route('management.fragments.functions') }}",
            tasksFragment:       "{{ route('management.fragments.tasks') }}"
        }
    };
</script>
@vite('resources/js/modules/management-modal.js')

@endpush

@endsection
