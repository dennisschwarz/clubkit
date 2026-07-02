<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Setting;

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core', 'is_active' => 1],
    ]);
    seedPermissions();
});

test('guest cannot access core settings', function () {
    $this->patch(route('admin.module-settings.core.update'))
        ->assertRedirect(route('login'));
});

test('super-admin can enable registration', function () {
    $admin = createSuperAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.module-settings.core.update'), ['registration_enabled' => '1'])
        ->assertRedirect();

    expect(Setting::getValue('registration_enabled'))->toBe('1');
});

test('super-admin can disable registration by omitting checkbox', function () {
    Setting::setValue('registration_enabled', '1');

    $admin = createSuperAdmin();

    // HTML forms do not send unchecked checkboxes — empty payload = disabled.
    $this->actingAs($admin)
        ->patch(route('admin.module-settings.core.update'), [])
        ->assertRedirect();

    expect(Setting::getValue('registration_enabled'))->toBe('0');
});
