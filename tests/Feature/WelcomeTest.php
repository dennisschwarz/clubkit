<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Setting;

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core', 'is_active' => 1],
    ]);
    seedPermissions();
});

test('welcome page renders for guests', function () {
    $this->get('/')->assertStatus(200);
});

test('authenticated user is redirected to dashboard from welcome page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('register button is hidden when registration is disabled', function () {
    Setting::setValue('registration_enabled', '0');

    $this->get('/')
        ->assertStatus(200)
        ->assertDontSee(route('register'));
});

test('register button is visible when registration is enabled', function () {
    Setting::setValue('registration_enabled', '1');

    $this->get('/')
        ->assertStatus(200)
        ->assertSee(route('register'));
});

test('register page redirects to welcome when registration is disabled', function () {
    Setting::setValue('registration_enabled', '0');

    $this->get('/register')
        ->assertRedirect(route('welcome'));
});

test('register post redirects to welcome when registration is disabled', function () {
    Setting::setValue('registration_enabled', '0');

    $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('welcome'));
});
