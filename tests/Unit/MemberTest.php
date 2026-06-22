<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\ExternalId;
use App\Models\Member;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    // ── Computed ──────────────────────────────────────────────────────────────

    public function test_full_name_returns_name_from_contact(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Lena',
            'last_name'  => 'Schmidt',
        ]);
        $member = Member::factory()->create(['contact_id' => $contact->id]);

        $this->assertSame('Lena Schmidt', $member->full_name);
    }

    public function test_has_login_returns_true_when_user_id_set(): void
    {
        $user   = User::factory()->create();
        $member = Member::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($member->hasLogin());
    }

    public function test_has_login_returns_false_when_user_id_null(): void
    {
        $member = Member::factory()->create(['user_id' => null]);

        $this->assertFalse($member->hasLogin());
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function test_players_scope_returns_only_players(): void
    {
        Member::factory()->create(['is_player' => true]);
        Member::factory()->create(['is_player' => false]);

        $this->assertCount(1, Member::players()->get());
    }

    public function test_with_login_scope_returns_only_members_with_login(): void
    {
        $user = User::factory()->create();
        Member::factory()->create(['user_id' => $user->id]);
        Member::factory()->create(['user_id' => null]);

        $this->assertCount(1, Member::withLogin()->get());
    }

    public function test_without_login_scope_returns_only_members_without_login(): void
    {
        $user = User::factory()->create();
        Member::factory()->create(['user_id' => $user->id]);
        Member::factory()->create(['user_id' => null]);

        $this->assertCount(1, Member::withoutLogin()->get());
    }

    // ── Soft Deletes ──────────────────────────────────────────────────────────

    public function test_member_uses_soft_deletes(): void
    {
        $member = Member::factory()->create();
        $member->delete();

        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function test_member_belongs_to_contact(): void
    {
        $contact = Contact::factory()->create();
        $member  = Member::factory()->create(['contact_id' => $contact->id]);

        $this->assertTrue($member->contact->is($contact));
    }

    public function test_member_belongs_to_user_optionally(): void
    {
        $user   = User::factory()->create();
        $member = Member::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($member->user->is($user));
    }

    public function test_member_without_user_has_null_user(): void
    {
        $member = Member::factory()->create(['user_id' => null]);

        $this->assertNull($member->user);
    }

    public function test_member_can_belong_to_multiple_teams(): void
    {
        $season = Season::factory()->create();
        $member = Member::factory()->create();
        $team1  = Team::factory()->create(['season_id' => $season->id, 'slug' => 'd1']);
        $team2  = Team::factory()->create(['season_id' => $season->id, 'slug' => 'd2']);

        $member->teams()->attach([$team1->id, $team2->id]);

        $this->assertCount(2, $member->teams);
    }

    public function test_member_has_external_ids(): void
    {
        $member = Member::factory()->create();
        ExternalId::factory()->create([
            'member_id'   => $member->id,
            'federation'  => 'dfbnet',
            'external_id' => 'DFB-12345',
        ]);

        $this->assertCount(1, $member->externalIds);
    }

    public function test_external_id_for_returns_correct_id(): void
    {
        $member = Member::factory()->create();
        ExternalId::factory()->create([
            'member_id'   => $member->id,
            'federation'  => 'dfbnet',
            'external_id' => 'DFB-99999',
        ]);

        $this->assertSame('DFB-99999', $member->externalIdFor('dfbnet'));
    }

    public function test_external_id_for_returns_null_for_unknown_federation(): void
    {
        $member = Member::factory()->create();

        $this->assertNull($member->externalIdFor('handball_net'));
    }
}
