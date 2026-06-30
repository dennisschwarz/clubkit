<?php

declare(strict_types=1);

namespace Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Treasury\Database\Factories\TreasuryCategoryFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a classification category for treasury transactions.
 *
 * Each category belongs to exactly one transaction_type (income or expense).
 * Examples: income → "Mitgliedsbeiträge", "Spende", "Verkauf"
 *           expense → "Anschaffung", "Spielbetrieb", "Strafe"
 *
 * The color field stores a badge colour token (e.g. 'green', 'red') that matches
 * the color prop accepted by the <x-ck-badge> Blade component.
 */
class TreasuryCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'transaction_type',
        'color',
        'created_by',
    ];

    protected static function newFactory(): TreasuryCategoryFactory
    {
        return TreasuryCategoryFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'transaction_type', 'color'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('treasury');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns all transactions classified under this category.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'category_id');
    }

    /**
     * Returns the user who created this category.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the minimal JS representation used in modal dropdowns.
     *
     * @return array{id: int, name: string, transaction_type: string, color: string|null}
     */
    public function toJsOption(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'transaction_type' => $this->transaction_type,
            'color'            => $this->color,
        ];
    }
}
