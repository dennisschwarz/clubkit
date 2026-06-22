<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    // ── Attribute ─────────────────────────────────────────────────────────────

    public function test_full_name_returns_first_and_last_name(): void
    {
        $contact = Contact::factory()->make([
            'first_name' => 'Anna',
            'last_name'  => 'Müller',
        ]);

        $this->assertSame('Anna Müller', $contact->full_name);
    }

    public function test_full_address_returns_formatted_string(): void
    {
        $contact = Contact::factory()->make([
            'street'      => 'Hauptstraße',
            'street_number' => '12',
            'postal_code' => '40764',
            'city'        => 'Langenfeld',
        ]);

        $this->assertSame('Hauptstraße 12, 40764 Langenfeld', $contact->full_address);
    }

    public function test_full_address_is_empty_without_street(): void
    {
        $contact = Contact::factory()->make(['street' => null]);

        $this->assertSame('', $contact->full_address);
    }

    // ── Fillable ──────────────────────────────────────────────────────────────

    public function test_contact_is_fillable_with_correct_fields(): void
    {
        $data = [
            'first_name'    => 'Max',
            'last_name'     => 'Mustermann',
            'phone'         => '0176123456',
            'email'         => 'max@example.de',
            'street'        => 'Musterstraße',
            'street_number' => '5a',
            'postal_code'   => '12345',
            'city'          => 'Musterstadt',
        ];

        $contact = new Contact($data);

        foreach ($data as $key => $value) {
            $this->assertSame($value, $contact->$key);
        }
    }

    // ── Soft Deletes ──────────────────────────────────────────────────────────

    public function test_contact_uses_soft_deletes(): void
    {
        $contact = Contact::factory()->create();
        $contact->delete();

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function test_contact_has_one_member(): void
    {
        $contact = Contact::factory()->create();
        $member  = Member::factory()->create(['contact_id' => $contact->id]);

        $this->assertTrue($contact->member->is($member));
    }

    public function test_contact_member_can_be_null(): void
    {
        $contact = Contact::factory()->create();

        $this->assertNull($contact->member);
    }
}
