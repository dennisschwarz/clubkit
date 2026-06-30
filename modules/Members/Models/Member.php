<?php

declare(strict_types=1);

namespace Modules\Members\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Members\Database\Factories\MemberFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a club member.
 *
 * Eligibility is stored as a date (eligible_to_play_date) rather than a boolean.
 * The computed boolean accessor eligible_to_play derives the current state from that date.
 * Eligibility can only be changed by updating the date column directly.
 *
 * The optional user_id column links this member to a User account for portal access.
 * A null user_id means the member has no login.
 *
 * Activity logging is configured to track all personally meaningful fields.
 * The computed accessor eligible_to_play is intentionally excluded from logging;
 * only the source column eligible_to_play_date is tracked.
 */
class Member extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'eligible_to_play_date',
        'status',
        'pass_number',
        'profile_image',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth'         => 'date',
        'eligible_to_play_date' => 'date',
    ];

    /**
     * @return MemberFactory
     */
    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * Only personally meaningful fields are tracked to avoid noise.
     * Profile images and internal columns (created_by, user_id) are excluded.
     * The log name matches the module slug for filtering in the admin panel.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'eligible_to_play_date',
                'status',
                'pass_number',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('members');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the user account linked to this member for portal access.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Returns the user who created this member record.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restricts the query to members with status 'active'.
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Returns "Last name, First name" display format.
     * Access via $member->full_name.
     *
     * @return Attribute
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->last_name . ', ' . $this->first_name,
        );
    }

    /**
     * Returns the member's age in completed years, or null if date of birth is not set.
     * Access via $member->age.
     *
     * @return Attribute
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_of_birth?->age,
        );
    }

    /**
     * Returns whether the member currently holds playing eligibility.
     * Access via $member->eligible_to_play.
     *
     * Rules:
     *   - eligible_to_play_date is null          → false (no eligibility on record)
     *   - eligible_to_play_date is in the future → false (eligibility not yet active)
     *   - eligible_to_play_date is today or past → true  (currently eligible)
     *
     * The set handler returns an empty array so that Eloquent never writes
     * this computed value to the database, regardless of how it is invoked.
     * Eligibility can only be changed by updating eligible_to_play_date directly.
     *
     * @return Attribute
     */
    protected function eligibleToPlay(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->eligible_to_play_date === null) {
                    return false;
                }
                return ! $this->eligible_to_play_date->isFuture();
            },
            set: fn ($value) => [],
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the minimal JS representation used in modal dropdowns.
     * Prevents duplication of the foreach pattern across controllers.
     *
     * @return array{id: int, name: string}
     */
    public function toJsOption(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->last_name . ', ' . $this->first_name,
        ];
    }
}
