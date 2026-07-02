<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Setting;

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core', 'is_active' => 1],
    ]);
    // Registration is disabled by default; enable it for these tests.
    Setting::setValue('registration_enabled', '1');
});

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
