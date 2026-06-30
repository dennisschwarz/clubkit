<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Represents an authenticated system user (admin, trainer, or club member with portal access).
 *
 * Activity logging is handled manually via an Observer registered in CoreServiceProvider,
 * not via the LogsActivity trait, to keep the User model free of module-specific concerns.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Returns the attribute casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the club member profile linked to this user account, if any.
     *
     * A user without a linked member is a pure admin (e.g. IT staff).
     * A user with a linked member is a club member who has portal access.
     *
     * @return HasOne<\Modules\Members\Models\Member, self>
     */
    public function member(): HasOne
    {
        return $this->hasOne(\Modules\Members\Models\Member::class, 'user_id');
    }
}
