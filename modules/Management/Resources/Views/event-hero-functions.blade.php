{{--
    Management hook: Vereinsfunktionen summary in the hero right column.
    Extension point : events.show.hero-right
    Registered by  : ManagementServiceProvider

    Variables provided by ManagementServiceProvider::composeEventHeroFunctions():
      $heroFunctions  array  [{name: string, member_name: string|null}]
--}}
@if(! empty($heroFunctions))
<div class="ck-hero-functions">
    <div class="ck-hero-functions__label">{{ __('events.detail.functions_label') }}</div>
    <div class="ck-hero-functions__list">
        @foreach($heroFunctions as $heroFn)
        <div class="ck-hero-function {{ $heroFn['member_name'] ? 'ck-hero-function--filled' : 'ck-hero-function--empty' }}">
            <span class="ck-hero-function__name">{{ $heroFn['name'] }}</span>
            @if($heroFn['member_name'])
                <x-ck-badge color="green">✓ {{ $heroFn['member_name'] }}</x-ck-badge>
            @else
                <x-ck-badge color="red">! {{ __('events.overview.unstaffed') }}</x-ck-badge>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif
