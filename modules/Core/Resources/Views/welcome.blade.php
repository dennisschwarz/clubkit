<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ckSettings['club_name'] ?? config('app.name', 'ClubKit') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ck-auth-body">

    <div class="ck-welcome-wrapper">

        {{-- Vereinslogo (wenn in den Einstellungen hinterlegt) --}}
        @php $logoPath = $ckSettings['logo_path'] ?? ''; @endphp
        @if (!empty($logoPath))
            <div class="ck-welcome-brand">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($logoPath) }}"
                     alt="{{ $ckSettings['club_name'] ?? config('app.name') }}"
                     class="ck-welcome-brand__logo">
            </div>
        @endif

        {{-- Vereinsname --}}
        <h1 class="ck-welcome-title">
            {{ $ckSettings['club_name'] ?? config('app.name', 'ClubKit') }}
        </h1>

        {{-- Untertitel --}}
        <p class="ck-welcome-subtitle">Vereinsverwaltung</p>

        {{-- Hinweis-Flash (z. B. "Registrierung deaktiviert") --}}
        @if (session('status'))
            <div class="ck-welcome-status">{{ session('status') }}</div>
        @endif

        {{-- Aktionen --}}
        <div class="ck-welcome-card">
            <a href="{{ route('login') }}" class="ck-btn ck-btn--primary ck-btn--block">
                Anmelden
            </a>

            @if ($registrationEnabled)
                <a href="{{ route('register') }}" class="ck-btn ck-btn--secondary ck-btn--block">
                    Registrieren
                </a>
            @endif
        </div>

    </div>

</body>
</html>
