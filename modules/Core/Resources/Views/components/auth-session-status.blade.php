{{--
    Auth Session Status – ersetzt die fehlende Breeze-Komponente.
    Zeigt z. B. "Passwort-Reset-Link gesendet" nach einem Passwort-Reset.
--}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'ck-flash ck-flash--success']) }}>
        {{ $status }}
    </div>
@endif
