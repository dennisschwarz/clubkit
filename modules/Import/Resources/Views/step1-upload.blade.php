@extends('core::admin.layout')
@section('title', 'Mitglieder importieren')

@section('content')
<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder importieren</h1>
        <p class="ck-page-subtitle">Schritt 1 von 3 – CSV-Datei hochladen</p>
    </div>
    <x-ck-button :href="route('members.index')" variant="secondary">
        ← Zurück zu Mitglieder
    </x-ck-button>
</div>

{{-- Fehlermeldungen --}}
@if ($errors->any())
    <div class="ck-alert ck-alert--danger ck-mb-4">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<x-ck-card>
    <x-slot:header>
        <span>CSV-Datei auswählen</span>
    </x-slot:header>

    <form method="POST"
          action="{{ route('import.upload') }}"
          enctype="multipart/form-data"
          class="ck-form-grid ck-form-grid--1">
        @csrf

        {{-- Unterstützte Formate --}}
        <div class="ck-import-formats">
            <p class="ck-label">Unterstützte Quellen</p>
            <div class="ck-row ck-row--gap">
                <x-ck-badge color="blue">DFBnet (Fußball)</x-ck-badge>
                <x-ck-badge color="gray">NuLiga (Handball) – demnächst</x-ck-badge>
            </div>
        </div>

        {{-- Datei-Upload --}}
        <x-ck-field
            type="file"
            name="csv_file"
            label="CSV-Datei"
            accept=".csv,.txt"
            :required="true"
        />

        <div class="ck-import-hint">
            <p>Die Datei wird analysiert und du kannst in den nächsten Schritten festlegen,
               welche Spalten welchen Mitgliederfeldern zugeordnet werden.</p>
            <p>Der Import findet erst nach deiner ausdrücklichen Bestätigung statt.</p>
        </div>

        <div class="ck-form-actions">
            <x-ck-button variant="primary" type="submit">
                Datei hochladen und analysieren →
            </x-ck-button>
        </div>
    </form>
</x-ck-card>
@endsection
