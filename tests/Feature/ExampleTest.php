<?php

// Grundlegende Verfügbarkeits-Tests für ClubKit.
// Der ursprüngliche Laravel-Default-Test prüfte GET / (keine Route in ClubKit).

test('login-Seite ist erreichbar', function () {
    $this->get('/login')->assertStatus(200);
});

test('nicht authentifizierter Zugriff auf dashboard landet auf login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
