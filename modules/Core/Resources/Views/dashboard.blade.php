@extends('core::admin.layout')
@section('title', 'Dashboard')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ __('core.dashboard.welcome', ['name' => auth()->user()->name]) }}</h1>
    </div>
</div>

<div class="ck-two-col-grid">

    {{-- Left: welcome message --}}
    <div>
        <x-ck-card>
            <x-slot:header>{{ __('core.dashboard.welcome_card_title') }}</x-slot:header>
            <div class="ck-prose">
                {!! nl2br(e(__('core.dashboard.welcome_text'))) !!}
            </div>
        </x-ck-card>
    </div>

    {{-- Right: stacked sections --}}
    <div>

        {{-- Recent activity --}}
        <div class="ck-mb-4">
            <x-ck-card>
                <x-slot:header>{{ __('core.dashboard.recent_activity') }}</x-slot:header>
                @if($recentActivity->isEmpty())
                <p class="ck-empty-state">{{ __('core.dashboard.no_activity') }}</p>
                @else
                <div class="ck-settings-section">
                    @foreach($recentActivity as $activity)
                    @php
                        [$badgeColor, $eventLabel] = match($activity->event ?? '') {
                            'created'  => ['green', __('Created')],
                            'updated'  => ['blue',  __('Updated')],
                            'deleted'  => ['red',   __('Deleted')],
                            'restored' => ['amber', __('Restored')],
                            default    => ['gray',  $activity->event ?? '–'],
                        };
                        $subject      = $activity->subject_type ? class_basename($activity->subject_type) : null;
                        $subjectId    = $activity->subject_id;
                        $module       = $activity->log_name && $activity->log_name !== 'default' ? $activity->log_name : null;
                        $changedCount = count($activity->properties->get('attributes', []));
                    @endphp
                    <div class="ck-settings-row">
                        <div>
                            <span class="ck-badge ck-badge--{{ $badgeColor }}">{{ $eventLabel }}</span>
                            @if($subject)
                            <span class="ck-ml-1"><strong>{{ $subject }}</strong>
                                @if($subjectId)<span class="ck-text-muted">#{{ $subjectId }}</span>@endif
                            </span>
                            @endif
                            @if($module)
                            <span class="ck-badge ck-badge--gray ck-ml-1">{{ $module }}</span>
                            @endif
                            @if($changedCount > 0)
                            <span class="ck-text-muted ck-ml-1">· {{ $changedCount }} {{ Str::plural('Feld', $changedCount) }}</span>
                            @endif
                        </div>
                        <div class="ck-text-muted ck-text-sm">
                            {{ $activity->created_at->diffForHumans() }}
                            @if($activity->causer)· {{ $activity->causer->name }}@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </x-ck-card>
        </div>

        {{-- Upcoming events --}}
        @if($upcomingEvents->isNotEmpty())
        <div class="ck-mb-4">
            <x-ck-card>
                <x-slot:header>{{ __('core.dashboard.upcoming_events') }}</x-slot:header>
                <div class="ck-settings-section">
                    @foreach($upcomingEvents as $event)
                    <div class="ck-settings-row">
                        <div class="ck-settings-row__label">
                            @if(Route::has('events.show'))
                            <a href="{{ route('events.show', $event->id) }}" class="ck-link">{{ $event->title }}</a>
                            @else
                            {{ $event->title }}
                            @endif
                        </div>
                        <div class="ck-text-muted ck-text-sm">
                            {{ \Carbon\Carbon::parse($event->starts_at)->format('d.m.Y H:i') }}
                            @if($event->location)· {{ $event->location }}@endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-ck-card>
        </div>
        @endif

        {{-- Recent members (permission-gated) --}}
        @if($recentMembers->isNotEmpty())
        <div class="ck-mb-4">
            <x-ck-card>
                <x-slot:header>{{ __('core.dashboard.recent_members') }}</x-slot:header>
                <div class="ck-settings-section">
                    @foreach($recentMembers as $member)
                    @php $statusColor = $member->status === 'active' ? 'green' : 'gray'; @endphp
                    <div class="ck-settings-row">
                        <div class="ck-settings-row__label">{{ $member->last_name }}, {{ $member->first_name }}</div>
                        <span class="ck-badge ck-badge--{{ $statusColor }}">{{ $member->status }}</span>
                    </div>
                    @endforeach
                </div>
                <x-slot:footer>
                    <a href="{{ route('members.index') }}" class="ck-link">{{ __('core.dashboard.all_members') }}</a>
                </x-slot:footer>
            </x-ck-card>
        </div>
        @endif

        {{-- Recent treasury transactions (permission-gated) --}}
        @if($recentTransactions->isNotEmpty())
        <div>
            <x-ck-card>
                <x-slot:header>{{ __('core.dashboard.recent_transactions') }}</x-slot:header>
                <div class="ck-settings-section">
                    @foreach($recentTransactions as $tx)
                    @php $txColor = $tx->type === 'income' ? 'green' : 'red'; @endphp
                    <div class="ck-settings-row">
                        <div class="ck-settings-row__label">{{ $tx->description }}</div>
                        <span class="ck-badge ck-badge--{{ $txColor }}">
                            {{ $tx->type === 'income' ? '+' : '-' }}{{ number_format(abs($tx->amount), 2, ',', '.') }} €
                        </span>
                    </div>
                    @endforeach
                </div>
            </x-ck-card>
        </div>
        @endif

        @ckHook('dashboard.quick-actions')

    </div>

</div>

@endsection