<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Globale Anwendungseinstellungen (Key-Value, gecacht).
 *
 * Tabelle: settings
 * Primary Key: key (string)
 * Kein timestamps-Felder – Werte sind statisch-konfigurativ.
 */
class Setting extends Model
{
    protected $table      = 'settings';
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = ['key', 'value'];

    // ── Lesen ──────────────────────────────────────────────────────────────

    /**
     * Einzelnen Wert lesen (gecacht, TTL 1 Stunde).
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember('ck_setting_' . $key, 3600, static function () use ($key, $default): mixed {
            $row = static::find($key);
            return $row !== null ? $row->value : $default;
        });
    }

    /**
     * Alle Settings als assoziatives Array ['key' => 'value'] (gecacht).
     * Wird vom CoreServiceProvider einmalig pro Request in $ckSettings geteilt.
     */
    public static function allCached(): array
    {
        return Cache::remember('ck_settings_all', 3600, static function (): array {
            return static::pluck('value', 'key')->toArray();
        });
    }

    // ── Schreiben ──────────────────────────────────────────────────────────

    /**
     * Einzelnen Wert setzen (INSERT oder UPDATE) und Cache invalidieren.
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('ck_setting_' . $key);
        Cache::forget('ck_settings_all');
    }

    /**
     * Mehrere Werte auf einmal setzen.
     *
     * @param array<string, mixed> $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        // Cache einmalig leeren nach Batch-Schreiben
        Cache::forget('ck_settings_all');
        foreach (array_keys($values) as $key) {
            Cache::forget('ck_setting_' . $key);
        }
    }
}
