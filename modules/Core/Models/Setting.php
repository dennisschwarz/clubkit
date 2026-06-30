<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Database\Factories\SettingFactory;

/**
 * Global application settings stored as key-value pairs (cached).
 *
 * Table   : settings
 * PK      : key (string, non-incrementing)
 * Timestamps: none — settings are configuration values, not time-tracked records
 *
 * Values are cached for 1 hour per key and also as a full collection.
 * Any write method must invalidate both caches.
 *
 * Note: ActivityLog is NOT used on the model. Changes are logged manually
 *       in AppearanceController so the full batch of changed keys is captured
 *       in one activity entry instead of one per key.
 */
class Setting extends Model
{
    use HasFactory;

    protected $table        = 'settings';
    protected $primaryKey   = 'key';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['key', 'value'];

    /**
     * @return SettingFactory
     */
    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Reads a single setting value (cached, TTL 1 hour).
     *
     * Cache key prefix: 'ck_setting_{key}'
     *
     * @param  string $key
     * @param  mixed  $default  Returned when the key does not exist in the database.
     * @return mixed
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember('ck_setting_' . $key, 3600, static function () use ($key, $default): mixed {
            $row = static::find($key);
            return $row !== null ? $row->value : $default;
        });
    }

    /**
     * Returns all settings as an associative array ['key' => 'value'] (cached).
     *
     * Loaded once per request by CoreServiceProvider and shared with all views
     * as the $ckSettings variable.
     *
     * Cache key: 'ck_settings_all'
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        return Cache::remember('ck_settings_all', 3600, static function (): array {
            return static::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Reads a single setting value and casts it to a boolean.
     *
     * Truthy string values: '1', 'true', 'yes', 'on' (case-insensitive).
     * All other non-null values are treated as false.
     * Returns $default when the key does not exist.
     *
     * @param  string $key
     * @param  bool   $default
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], strict: true);
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Sets a single value (INSERT or UPDATE) and invalidates its cache entry.
     *
     * Invalidates:
     *   - 'ck_setting_{key}'
     *   - 'ck_settings_all'
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('ck_setting_' . $key);
        Cache::forget('ck_settings_all');
    }

    /**
     * Sets multiple values at once and invalidates all affected cache entries.
     *
     * @param  array<string, mixed> $values
     * @return void
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('ck_settings_all');
        foreach (array_keys($values) as $key) {
            Cache::forget('ck_setting_' . $key);
        }
    }
}