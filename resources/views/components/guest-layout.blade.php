<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'ClubKit') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="ck-auth-body">
        <div class="ck-auth-wrapper">
            <div class="ck-auth-logo">
                <span>{{ config('app.name', 'ClubKit') }}</span>
            </div>
            <div class="ck-auth-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
