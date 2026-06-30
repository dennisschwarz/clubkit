<?php

declare(strict_types=1);

// Basic availability tests for ClubKit.
// The original Laravel default test checked GET / (no such route in ClubKit).

test('login page is reachable', function () {
    $this->get('/login')->assertStatus(200);
});

test('unauthenticated access to dashboard redirects to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
