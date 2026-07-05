<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Creates an Event without explicitly setting optional fields. */
function makeEvent(array $overrides = []): Event
{
    return Event::create(array_merge([
        'title'      => 'Testtermin',
        'starts_at'  => now()->addDays(7),
        'ends_at'    => null,
        'created_by' => null,
    ], $overrides));
}

// ── Create ────────────────────────────────────────────────────────────────────

test('an event can be created', function () {
    $event = makeEvent(['title' => 'Pokalfinale']);

    expect(Event::where('id', $event->id)->where('title', 'Pokalfinale')->exists())->toBeTrue();
});

test('optional fields may be null', function () {
    $event = makeEvent();

    expect($event->description)->toBeNull();
    expect($event->ends_at)->toBeNull();
    expect($event->location)->toBeNull();
    expect($event->notes)->toBeNull();
});

test('starts_at and ends_at are cast to Carbon instances', function () {
    $event = makeEvent(['starts_at' => '2027-07-15 10:00:00', 'ends_at' => '2027-07-15 18:00:00']);

    expect($event->fresh()->starts_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($event->fresh()->ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('creator relation returns the user who created the event', function () {
    $user  = User::factory()->create();
    $event = makeEvent(['created_by' => $user->id]);

    expect($event->fresh()->creator->id)->toBe($user->id);
});

// ── scopeUpcoming / scopePast ─────────────────────────────────────────────────

test('scopeUpcoming returns only future events', function () {
    $future = makeEvent(['title' => 'Future', 'starts_at' => now()->addDays(1)]);
    $past   = makeEvent(['title' => 'Past',   'starts_at' => now()->subDays(1)]);

    $upcoming = Event::upcoming()->get();
    expect($upcoming->contains($future))->toBeTrue();
    expect($upcoming->contains($past))->toBeFalse();
});

test('scopePast returns only past events', function () {
    $future = makeEvent(['title' => 'Future', 'starts_at' => now()->addDays(1)]);
    $past   = makeEvent(['title' => 'Past',   'starts_at' => now()->subDays(1)]);

    $pastEvents = Event::past()->get();
    expect($pastEvents->contains($past))->toBeTrue();
    expect($pastEvents->contains($future))->toBeFalse();
});

// ── Activity Log ──────────────────────────────────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S9

test('updating title is logged in activity log', function () {
    $event = makeEvent(['title' => 'Alt']);
    $event->update(['title' => 'Neu']);

    // Filter explicitly for the 'updated' event.
    $log = Activity::where('subject_type', Event::class)
        ->where('subject_id', $event->id)
        ->where('log_name', 'events')
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($log->attribute_changes['attributes']['title'])->toBe('Neu');
    expect($log->attribute_changes['old']['title'])->toBe('Alt');
});

test('unchanged fields do not produce a log entry', function () {
    $event = makeEvent(['title' => 'Unverändert']);
    $initialCount = Activity::where('subject_id', $event->id)->count();

    $event->update(['title' => 'Unverändert']);

    expect(Activity::where('subject_id', $event->id)->count())->toBe($initialCount);
});
