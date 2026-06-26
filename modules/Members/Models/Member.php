<?php

declare(strict_types=1);

namespace Modules\Members\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Members\Database\Factories\MemberFactory;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'eligible_to_play_date',  // ersetzt eligible_to_play (boolean)
        'status',
        'pass_number',
        'profile_image',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth'        => 'date',
        'eligible_to_play_date' => 'date',  // Carbon-Objekt für ->isFuture(), ->format() etc.
    ];

    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getFullNameAttribute(): string
    {
        return $this->last_name . ', ' . $this->first_name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Abgeleitetes Attribut: Ist das Mitglied heute spielberechtigt?
     *
     * Regeln:
     *  - eligible_to_play_date ist NULL                 → false (kein Spielrecht)
     *  - eligible_to_play_date liegt in der Zukunft     → false (noch nicht berechtigt)
     *  - eligible_to_play_date ist heute oder vergangen → true  (berechtigt)
     *
     * Damit bleibt $member->eligible_to_play im gesamten Code gültig –
     * Views, Controller und JS müssen NICHT geändert werden.
     */
    public function getEligibleToPlayAttribute(): bool
    {
        if ($this->eligible_to_play_date === null) {
            return false;
        }

        // isFuture() = strikt in der Zukunft → !isFuture() = heute oder vergangen
        return ! $this->eligible_to_play_date->isFuture();
    }
}
