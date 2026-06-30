<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Import\MemberData;
use Modules\Import\Models\MemberImportLog;
use Modules\Import\Services\MemberImportService;
use Modules\Members\Models\Member;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * Creates a MemberData DTO with sensible default values.
 *
 * Uses eligible_to_play_date (YYYY-MM-DD) instead of the old boolean eligible_to_play.
 * Default: yesterday → member is currently eligible.
 * Override: makeMemberData(['eligible_to_play_date' => null]) → not eligible.
 *
 * @param  array $overrides
 * @return MemberData
 */
function makeMemberData(array $overrides = []): MemberData
{
    // array_merge instead of ?? so that null can be passed as an explicit override
    $data = array_merge([
        'first_name'            => 'Maryam',
        'last_name'             => 'Akhabach',
        'date_of_birth'         => '2012-09-08',
        'gender'                => 'female',
        'pass_number'           => '0765-0056',
        'eligible_to_play_date' => now()->subDay()->toDateString(),
        'status'                => 'active',
        'custom_fields'         => [],
    ], $overrides);

    return new MemberData(
        first_name:            $data['first_name'],
        last_name:             $data['last_name'],
        date_of_birth:         $data['date_of_birth'],
        gender:                $data['gender'],
        pass_number:           $data['pass_number'],
        eligible_to_play_date: $data['eligible_to_play_date'],
        status:                $data['status'],
        custom_fields:         $data['custom_fields'],
    );
}

/**
 * Creates an existing member directly in the database.
 *
 * Uses eligible_to_play_date instead of the old boolean eligible_to_play.
 *
 * @param  array $attrs
 * @return Member
 */
function makeExistingMember(array $attrs = []): Member
{
    return Member::create(array_merge([
        'first_name'            => 'Maryam',
        'last_name'             => 'Akhabach',
        'date_of_birth'         => '2012-09-08',
        'gender'                => 'female',
        'pass_number'           => '0765-0056',
        'eligible_to_play_date' => now()->subDay()->toDateString(),
        'status'                => 'active',
        'created_by'            => null,
    ], $attrs));
}

// ── compare(): new member ─────────────────────────────────────────────────────

test('compare gibt new zurück wenn kein mitglied in der db existiert', function () {
    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData());

    expect($result['status'])->toBe('new');
    expect($result['existing_id'])->toBeNull();
    expect($result['diff'])->toBe([]);
});

// ── compare(): unchanged ──────────────────────────────────────────────────────

test('compare gibt unchanged zurück wenn alle felder übereinstimmen', function () {
    makeExistingMember();

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData());

    // Check diff first: Pest shows the actual diff content when this test fails
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare findet mitglied per passnummer', function () {
    // Name intentionally different – pass number takes priority
    $member = makeExistingMember(['first_name' => 'Maria']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['first_name' => 'Maryam']));

    expect($result['existing_id'])->toBe($member->id);
    expect($result['status'])->not->toBe('new');
});

test('compare findet mitglied per name und geburtsdatum wenn keine passnummer', function () {
    makeExistingMember(['pass_number' => null]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['pass_number' => null]));

    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare gibt new zurück wenn weder passnummer noch name+dob übereinstimmen', function () {
    makeExistingMember(['pass_number' => '9999-0000', 'last_name' => 'AnderesName']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['pass_number' => '0765-0056']));

    expect($result['status'])->toBe('new');
});

// ── compare(): changed ────────────────────────────────────────────────────────

test('compare gibt changed zurück wenn felder abweichen', function () {
    makeExistingMember(['gender' => 'male']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['gender' => 'female']));

    expect($result['status'])->toBe('changed');
    expect($result['diff'])->toHaveKey('gender');
    expect($result['diff']['gender']['old'])->toBe('male');
    expect($result['diff']['gender']['new'])->toBe('female');
});

test('compare normalisiert carbon-datumsobjekt korrekt', function () {
    // Reproduces the bug: $existing->date_of_birth is a Carbon object.
    // Wrong normalisation via (string) Carbon would yield "2012-09-08 00:00:00" != "2012-09-08".
    makeExistingMember(['date_of_birth' => '2012-09-08']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['date_of_birth' => '2012-09-08']));

    expect($result['diff'])->not->toHaveKey('date_of_birth');
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare normalisiert eligible_to_play_date korrekt', function () {
    // Both sides have the same date → no diff.
    // Also tests that the Carbon date from the DB is correctly normalised to YYYY-MM-DD.
    $date = '2025-01-15';
    makeExistingMember(['eligible_to_play_date' => $date]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['eligible_to_play_date' => $date]));

    expect($result['diff'])->not->toHaveKey('eligible_to_play_date');
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare erkennt geänderte eligible_to_play_date korrekt', function () {
    // Old date in DB, new date in import → changed with diff
    makeExistingMember(['eligible_to_play_date' => '2024-01-01']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['eligible_to_play_date' => '2025-06-01']));

    expect($result['status'])->toBe('changed');
    expect($result['diff'])->toHaveKey('eligible_to_play_date');
    expect($result['diff']['eligible_to_play_date']['old'])->toBe('2024-01-01');
    expect($result['diff']['eligible_to_play_date']['new'])->toBe('2025-06-01');
});

test('compare erkennt null eligible_to_play_date korrekt', function () {
    // DB: no date → import provides date → changed
    makeExistingMember(['eligible_to_play_date' => null]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['eligible_to_play_date' => '2025-06-01']));

    expect($result['status'])->toBe('changed');
    expect($result['diff'])->toHaveKey('eligible_to_play_date');
});

// ── execute(): run the import ─────────────────────────────────────────────────

test('execute legt neuen member an und schreibt import-log', function () {
    $user    = createPlainUser();
    $service = new MemberImportService();

    $processedRows = [
        0 => [
            'mapped'        => [
                'first_name'            => 'Maryam',
                'last_name'             => 'Akhabach',
                'date_of_birth'         => '2012-09-08',
                'gender'                => 'female',
                'pass_number'           => '0765-0056',
                'eligible_to_play_date' => now()->subDay()->toDateString(),
                'status'                => 'active',
            ],
            'custom_fields' => [],
            'status'        => 'new',
            'existing_id'   => null,
            'diff'          => [],
        ],
    ];

    $stats = $service->execute(
        processedRows:   $processedRows,
        selectedIndexes: [0],
        source:          'dfbnet',
        filename:        'test.csv',
        importedBy:      $user->id,
    );

    expect($stats['created'])->toBe(1);
    expect($stats['updated'])->toBe(0);

    expect(Member::where('last_name', 'Akhabach')->exists())->toBeTrue();
    expect(MemberImportLog::where('source', 'dfbnet')->exists())->toBeTrue();
});

test('execute aktualisiert bestehenden member bei status changed', function () {
    $user   = createPlainUser();
    $member = makeExistingMember(['gender' => 'male', 'created_by' => $user->id]);

    $service = new MemberImportService();

    $processedRows = [
        0 => [
            'mapped'        => [
                'first_name'            => 'Maryam',
                'last_name'             => 'Akhabach',
                'date_of_birth'         => '2012-09-08',
                'gender'                => 'female',
                'pass_number'           => '0765-0056',
                'eligible_to_play_date' => now()->subDay()->toDateString(),
                'status'                => 'active',
            ],
            'custom_fields' => [],
            'status'        => 'changed',
            'existing_id'   => $member->id,
            'diff'          => ['gender' => ['old' => 'male', 'new' => 'female']],
        ],
    ];

    $stats = $service->execute(
        processedRows:   $processedRows,
        selectedIndexes: [0],
        source:          'dfbnet',
        filename:        'test.csv',
        importedBy:      $user->id,
    );

    expect($stats['updated'])->toBe(1);
    expect($stats['created'])->toBe(0);

    $member->refresh();
    expect($member->gender)->toBe('female');
});

test('execute überspringt nicht ausgewählte zeilen', function () {
    $user    = createPlainUser();
    $service = new MemberImportService();

    $processedRows = [
        0 => [
            'mapped'        => ['first_name' => 'A', 'last_name' => 'B', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play_date' => null, 'status' => 'active'],
            'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => [],
        ],
        1 => [
            'mapped'        => ['first_name' => 'C', 'last_name' => 'D', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play_date' => null, 'status' => 'active'],
            'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => [],
        ],
    ];

    $stats = $service->execute(
        processedRows:   $processedRows,
        selectedIndexes: [0],
        source:          'dfbnet',
        filename:        'test.csv',
        importedBy:      $user->id,
    );

    expect($stats['created'])->toBe(1);
    expect($stats['skipped'])->toBe(1);
    expect(Member::where('last_name', 'B')->exists())->toBeTrue();
    expect(Member::where('last_name', 'D')->exists())->toBeFalse();
});

test('execute rollt alles zurück wenn ein datensatz fehlschlägt', function () {
    $user    = createPlainUser();
    $service = new MemberImportService();

    // Second entry has an invalid existing_id → findOrFail throws ModelNotFoundException
    $processedRows = [
        0 => [
            'mapped'        => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play_date' => null, 'status' => 'active'],
            'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => [],
        ],
        1 => [
            'mapped'        => ['first_name' => 'X', 'last_name' => 'Y', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play_date' => null, 'status' => 'active'],
            'custom_fields' => [], 'status' => 'changed', 'existing_id' => 99999, 'diff' => ['gender' => ['old' => 'male', 'new' => 'female']],
        ],
    ];

    $threw = false;
    try {
        $service->execute(
            processedRows:   $processedRows,
            selectedIndexes: [0, 1],
            source:          'dfbnet',
            filename:        'test.csv',
            importedBy:      $user->id,
        );
    } catch (\Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue();

    // Due to the transaction: no member created, no log
    expect(Member::where('last_name', 'Akhabach')->exists())->toBeFalse();
    expect(MemberImportLog::where('source', 'dfbnet')->exists())->toBeFalse();
});
