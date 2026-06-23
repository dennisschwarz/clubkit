@extends('core::admin.layout')

@section('title', 'Mitglied anlegen')

@section('content')
<div class="max-w-lg space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mitglied anlegen</h1>
        <p class="text-sm text-gray-500 mt-1">
            <a href="{{ route('members.index') }}" class="text-blue-600 hover:underline">&larr; Zurück zur Liste</a>
        </p>
    </div>

    <form method="POST" action="{{ route('members.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Vorname *</label>
            <input type="text" name="first_name" value="{{ old('first_name') }}"
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('first_name') border-red-400 @enderror"
                   required autofocus>
            @error('first_name')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nachname *</label>
            <input type="text" name="last_name" value="{{ old('last_name') }}"
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('last_name') border-red-400 @enderror"
                   required>
            @error('last_name')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Geburtsdatum</label>
            <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                   max="{{ now()->subDay()->format('Y-m-d') }}"
                   class="w-full border rounded-lg px-3 py-2 text-sm @error('date_of_birth') border-red-400 @enderror">
            @error('date_of_birth')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
                Mitglied anlegen
            </button>
            <a href="{{ route('members.index') }}"
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-semibold rounded-lg">
                Abbrechen
            </a>
        </div>
    </form>

</div>
@endsection
