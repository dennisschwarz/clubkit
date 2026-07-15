@extends('core::admin.layout')
@section('title', 'Modul-Einstellungen')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Modul-Einstellungen</h1>
        <p class="ck-page-subtitle">Konfiguration der installierten Module</p>
    </div>
</div>

{{-- Tab bar: each installed module with settings registers a button via admin.module-settings.tabs --}}
<div class="ck-local-tabs ck-mb-5">
    @ckHook('admin.module-settings.tabs')
</div>

{{-- Section content: each module renders a ck-local-section div via admin.module-settings.sections --}}
@ckHook('admin.module-settings.sections')

@if(! app('ck.hooks')->has('admin.module-settings.sections'))
<p class="ck-empty-state">Kein installiertes Modul hat Einstellungen hinterlegt.</p>
@endif

@endsection
