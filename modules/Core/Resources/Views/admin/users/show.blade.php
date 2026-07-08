@extends('core::admin.layout')

@section('title', 'Nutzer: ' . $user->name)

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">{{ $user->name }}</h1>
        <p class="ck-page-subtitle">
            <a href="{{ route('admin.users.index') }}" class="ck-link">{{ __('core.back') }}</a>
        </p>
    </div>
</div>

<div class="ck-two-col-grid">

    {{-- ── Login credentials ─────────────────────────────────────────────────────── --}}
    <x-ck-card>
        <x-slot:header>{{ __('core.users.tab_login') }}</x-slot:header>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')
            <div class="ck-form-grid ck-form-grid--2">
                <x-ck-field label="Name"   name="name"  id="showFieldName"
                    :value="old('name', $user->name)"   :required="true" />
                <x-ck-field :label="__('auth.email')" name="email" id="showFieldEmail"
                    type="email"
                    :value="old('email', $user->email)" :required="true" />
                <x-ck-field :label="__('core.users.password_new')" name="password" type="password"
                    id="showFieldPassword"
                    :hint="__('core.users.password_hint')" />
                <x-ck-field :label="__('core.users.password_repeat')" name="password_confirmation"
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
        <x-slot:header>{{ __('core.users.tab_rights') }}</x-slot:header>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="rights_only" value="1">
            <div class="ck-field">
                <label class="ck-field__label" for="showRoleSelect">{{ __('core.users.assign_role') }}</label>
                <select name="role" id="showRoleSelect" class="ck-field__input">
                    <option value="">{{ __('core.users.no_role') }}</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}"
                        {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                        {{ ucfirst($role->name) }}
                        @if($role->name === 'super-admin') ({{ __('core.roles.full_access') }})@endif
                    </option>
                    @endforeach
                </select>
            </div>
            @if($user->roles->isEmpty() && $user->permissions->isNotEmpty())
            <p class="ck-text-muted ck-mt-2">
                {{ __('core.users.custom_perms_hint') }}
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
    <x-slot:header>{{ __('core.users.delete_section') }}</x-slot:header>
    <p class="ck-text-muted">{{ __('core.users.delete_warning') }}</p>
    <div class="ck-form-actions ck-mt-3">
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
            @csrf
            @method('DELETE')
            <x-ck-button
                type="submit"
                variant="danger"
                :confirm="__('core.users.confirm_delete', ['name' => $user->name])">
                {{ __('Delete user') }}
            </x-ck-button>
        </form>
    </div>
</x-ck-card>
@endif

@endsection
