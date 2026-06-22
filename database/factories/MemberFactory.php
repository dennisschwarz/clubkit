<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
final class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'user_id'    => null,   // kein Login by default
            'is_player'  => true,
            'notes'      => null,
        ];
    }

    /**
     * Member mit einem Login-Account.
     */
    public function withLogin(): static
    {
        return $this->state([
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Member ohne Login (explizit).
     */
    public function withoutLogin(): static
    {
        return $this->state(['user_id' => null]);
    }

    /**
     * Member ist Spieler.
     */
    public function player(): static
    {
        return $this->state(['is_player' => true]);
    }

    /**
     * Member ist kein Spieler (z.B. Elternteil, Betreuer).
     */
    public function nonPlayer(): static
    {
        return $this->state(['is_player' => false]);
    }

    /**
     * Member mit einem bestehenden Contact-Datensatz.
     */
    public function forContact(Contact $contact): static
    {
        return $this->state(['contact_id' => $contact->id]);
    }
}
