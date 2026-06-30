<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Factory ────────────────────────────────────────────────────────────────────

test('setting factory creates a valid key-value pair', function () {
    $setting = Setting::factory()->create();

    expect($setting->key)->toBeString()
        ->and($setting->value)->toBeString();
});

test('setting factory forKey state creates a specific key-value pair', function () {
    // Use a key NOT pre-seeded by migrations (e.g. add_extended_appearance_settings
    // inserts 'club_name', 'primary_color' etc. → avoid those to prevent UNIQUE violations).
    $setting = Setting::factory()->forKey('test_forkey_setting', 'FC Test')->create();

    expect($setting->key)->toBe('test_forkey_setting')
        ->and($setting->value)->toBe('FC Test');
});

// ── Read ───────────────────────────────────────────────────────────────────────

test('getValue returns the stored value', function () {
    Setting::factory()->forKey('test_key', 'hello')->create();
    Cache::flush();

    expect(Setting::getValue('test_key'))->toBe('hello');
});

test('getValue returns null for a missing key', function () {
    Cache::flush();

    expect(Setting::getValue('key_that_does_not_exist_xyz'))->toBeNull();
});

test('getValue returns the default value for a missing key', function () {
    Cache::flush();

    expect(Setting::getValue('missing_key_abc', 'default'))->toBe('default');
});

// ── Cache ──────────────────────────────────────────────────────────────────────
//
// Cache-key pattern: 'ck_setting_{key}' (single) | 'ck_settings_all' (collection)
// Defined in Setting::getValue() and Setting::allCached() respectively.

test('getValue is cached after first call', function () {
    Setting::factory()->forKey('cached_key', 'cached_value')->create();
    Cache::flush();

    Setting::getValue('cached_key');

    // Model uses 'ck_setting_' prefix — NOT 'setting_'
    expect(Cache::has('ck_setting_cached_key'))->toBeTrue();
});

// ── allCached ─────────────────────────────────────────────────────────────────

test('allCached returns all settings as a key-value map', function () {
    Setting::factory()->forKey('all_a', '1')->create();
    Setting::factory()->forKey('all_b', '2')->create();

    $all = Setting::allCached();

    expect($all)->toHaveKey('all_a')
        ->and($all['all_a'])->toBe('1')
        ->and($all)->toHaveKey('all_b')
        ->and($all['all_b'])->toBe('2');
});

// ── Update ────────────────────────────────────────────────────────────────────

test('setValue updates an existing setting and invalidates cache', function () {
    Setting::factory()->forKey('update_key', 'old')->create();
    Cache::flush();

    Setting::getValue('update_key'); // populate cache
    Setting::setValue('update_key', 'new');

    Cache::flush();
    expect(Setting::getValue('update_key'))->toBe('new');
});

// ── Cache invalidation ────────────────────────────────────────────────────────

test('setValue invalidates the cache for the updated key', function () {
    Setting::factory()->forKey('inv_key', 'original')->create();
    Cache::flush();

    Setting::getValue('inv_key');
    // Model uses 'ck_setting_' prefix — NOT 'setting_'
    expect(Cache::has('ck_setting_inv_key'))->toBeTrue();

    Setting::setValue('inv_key', 'updated');
    expect(Cache::has('ck_setting_inv_key'))->toBeFalse();
});

test('setValue invalidates the all-settings cache', function () {
    Setting::factory()->forKey('all_inv', 'before')->create();
    Cache::flush();

    Setting::allCached();
    // Model uses 'ck_settings_all' — NOT 'settings_all'
    expect(Cache::has('ck_settings_all'))->toBeTrue();

    Setting::setValue('all_inv', 'after');
    expect(Cache::has('ck_settings_all'))->toBeFalse();
});

// ── Typed helpers ─────────────────────────────────────────────────────────────

test('getBool returns true for truthy string values', function () {
    foreach (['1', 'true', 'yes', 'on'] as $val) {
        Setting::factory()->forKey('bool_key_' . $val, $val)->create();
        Cache::flush();
        expect(Setting::getBool('bool_key_' . $val))->toBeTrue();
    }
});

test('getBool returns false for falsy string values', function () {
    Setting::factory()->forKey('bool_false_key', '0')->create();
    Cache::flush();

    expect(Setting::getBool('bool_false_key'))->toBeFalse();
});

// ── Multi-update ──────────────────────────────────────────────────────────────

test('setMany updates multiple settings at once', function () {
    Setting::factory()->forKey('multi_key', 'old')->create();
    Cache::flush();

    Setting::setMany(['multi_key' => 'batch_updated', 'new_key_xyz' => 'created']);

    Cache::flush();
    expect(Setting::getValue('multi_key'))->toBe('batch_updated');
    expect(Setting::getValue('new_key_xyz'))->toBe('created');
});
