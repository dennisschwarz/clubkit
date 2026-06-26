@extends('core::admin.layout')
@section('title', 'Modul-Einstellungen')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Modul-Einstellungen</h1>
        <p class="ck-page-subtitle">Konfiguration der installierten Module</p>
    </div>
</div>

{{--
    Extension Point: Jedes Modul, das Einstellungen anbietet,
    registriert hier über den Hook 'admin.module-settings.sections' seine Sektion.
    Jede Sektion ist eine eigenständige <x-ck-card> mit eigenen Forms.
--}}
@ckHook('admin.module-settings.sections')

@if(!app('ck.hooks')->has('admin.module-settings.sections'))
    <x-ck-card>
        <div class="ck-empty-state">
            Kein installiertes Modul hat Einstellungen hinterlegt.
        </div>
    </x-ck-card>
@endif

@endsection
