@extends('core::admin.layout')

@section('title', 'Nutzer: ' . $user->name)

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ $user->name }}</h1>
        <p class="ck-page-subtitle">
            <a href="{{ route('admin.users.index') }}" class="ck-link">← Zurück zur Übersicht</a>
        </p>
    </div>
</div>

<div class="ck-two-col-grid">

    {{-- ── Login credentials ─────────────────────────────────────────────────────── --}}
    <x-ck-card>
        <x-slot:header>🔑 Login-Daten</x-slot:header>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')
            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Name"   name="name"  id="showFieldName"
                    :value="old('name', $user->name)"   :required="true" />
                <x-ck-field label="E-Mail" name="email" id="showFieldEmail"
                    type="email"
                    :value="old('email', $user->email)" :required="true" />
                <x-ck-field label="Neues Passwort" name="password" type="password"
                    id="showFieldPassword"
                    hint="(leer lassen = nicht ändern)" />
                <x-ck-field label="Passwort wiederholen" name="password_confirmation"
                    type="password" />
            </div>
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Save') }}</x-ck-button>
                <x-ck-button variant="secondary" href="{{ route('admin.users.index') }}">
                    {{ __('Cancel') }}
                </x-ck-button>
            </div>
        </form>
    </x-ck-card>

    {{-- ── Roles & permissions ─────────────────────────────────────────────────── --}}
    <x-ck-card>
        <x-slot:header>🔒 Rollen &amp; Rechte</x-slot:header>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="rights_only" value="1">
            <div class="ck-field">
                <label class="ck-field__label" for="showRoleSelect">Rolle zuweisen</label>
                <select name="role" id="showRoleSelect" class="ck-field__input">
                    <option value="">– Keine Rolle –</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}"
                        {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                        {{ ucfirst($role->name) }}
                        @if($role->name === 'super-admin') (Vollzugriff)@endif
                    </option>
                    @endforeach
                </select>
            </div>
            @if($user->roles->isEmpty() && $user->permissions->isNotEmpty())
            <p class="ck-text-muted ck-mt-2">
                Dieser Nutzer hat benutzerdefinierte Einzelrechte.
                Rollenzuweisung überschreibt diese.
            </p>
            @endif
            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary">{{ __('Save permissions') }}</x-ck-button>
            </div>
        </form>
    </x-ck-card>

</div>

{{-- ── Delete user ──────────────────────────────────────────────────────── --}}
@if($user->id !== auth()->id())
<x-ck-card class="ck-mt-5" accent="red">
    <x-slot:header>⚠️ Nutzer löschen</x-slot:header>
    <p class="ck-text-muted">
        Dieser Nutzer und alle seine Sitzungsdaten werden unwiderruflich gelöscht.
    </p>
    <div class="ck-form-actions ck-mt-3">
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
            @csrf
            @method('DELETE')
            <x-ck-button
                type="submit"
                variant="danger"
                :confirm="'Nutzer ' . $user->name . ' wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.'">
                {{ __('Delete user') }}
            </x-ck-button>
        </form>
    </div>
</x-ck-card>
@endif

@endsection
