@extends('core::admin.layout')

@section('title', 'Nutzer bearbeiten')

@section('content')
<div class="max-w-lg space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">&larr; Zurück zur Liste</a>
        </p>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user) }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PATCH')

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name *</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" autofocus required
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('name') border-red-400 @enderror">
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">E-Mail *</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('email') border-red-400 @enderror">
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                Neues Passwort
                <span class="text-gray-400 font-normal normal-case">(leer lassen = nicht ändern)</span>
            </label>
            <input type="password" name="password"
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('password') border-red-400 @enderror">
            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Passwort wiederholen</label>
            <input type="password" name="password_confirmation"
                   class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Rollen</label>
            <div class="space-y-2">
                @foreach($roles as $role)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                           {{ $user->hasRole($role->name) ? 'checked' : '' }}
                           class="accent-blue-600">
                    <span class="text-sm text-gray-700">{{ $role->name }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
                Speichern
            </button>
            <a href="{{ route('admin.users.index') }}"
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-semibold rounded-lg">
                Abbrechen
            </a>
        </div>
    </form>

    <!-- Löschen -->
    @if($user->id !== auth()->id())
    <div class="bg-red-50 border border-red-200 rounded-xl p-5">
        <h3 class="text-sm font-bold text-red-700 mb-2">Nutzer löschen</h3>
        <p class="text-xs text-red-600 mb-3">Dieser Nutzer und alle seine Daten werden unwiderruflich gelöscht.</p>
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
              onsubmit="return confirm('Nutzer {{ $user->name }} wirklich löschen?')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg">
                Nutzer löschen
            </button>
        </form>
    </div>
    @endif

</div>
@endsection
