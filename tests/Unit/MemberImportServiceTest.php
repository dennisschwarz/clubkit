<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Import\MemberData;
use Modules\Import\Models\MemberImportLog;
use Modules\Import\Services\MemberImportService;
use Modules\Members\Models\Member;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Fixtures ──────────────────────────────────────────────────────────────────

function makeMemberData(array $overrides = []): MemberData
{
    // array_merge statt ?? – so kann null als expliziter Override übergeben werden.
    // Beispiel: makeMemberData(['pass_number' => null])
    //   Mit ??: null ?? '0765-0056' = '0765-0056'  ← FALSCH
    //   Mit array_merge: pass_number wird zu null  ← RICHTIG
    $data = array_merge([
        'first_name'       => 'Maryam',
        'last_name'        => 'Akhabach',
        'date_of_birth'    => '2012-09-08',
        'gender'           => 'female',
        'pass_number'      => '0765-0056',
        'eligible_to_play' => true,
        'status'           => 'active',
        'custom_fields'    => [],
    ], $overrides);

    return new MemberData(
        first_name:       $data['first_name'],
        last_name:        $data['last_name'],
        date_of_birth:    $data['date_of_birth'],
        gender:           $data['gender'],
        pass_number:      $data['pass_number'],
        eligible_to_play: $data['eligible_to_play'],
        status:           $data['status'],
        custom_fields:    $data['custom_fields'],
    );
}

function makeExistingMember(array $attrs = []): Member
{
    return Member::create(array_merge([
        'first_name'       => 'Maryam',
        'last_name'        => 'Akhabach',
        'date_of_birth'    => '2012-09-08',
        'gender'           => 'female',
        'pass_number'      => '0765-0056',
        'eligible_to_play' => true,
        'status'           => 'active',
        'created_by'       => null,
    ], $attrs));
}

// ── compare(): Neuer Member ───────────────────────────────────────────────────

test('compare gibt new zurück wenn kein mitglied in der db existiert', function () {
    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData());

    expect($result['status'])->toBe('new');
    expect($result['existing_id'])->toBeNull();
    expect($result['diff'])->toBe([]);
});

// ── compare(): Unverändert ────────────────────────────────────────────────────

test('compare gibt unchanged zurück wenn alle felder übereinstimmen', function () {
    makeExistingMember();

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData());

    // diff ZUERST prüfen: Pest zeigt den tatsächlichen Diff-Inhalt wenn dieser Test fehlschlägt.
    // Damit kann direkt abgelesen werden, welches Feld den Unterschied auslöst.
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare findet mitglied per passnummer', function () {
    // Name absichtlich abweichend – Passnummer hat Priorität
    $member = makeExistingMember(['first_name' => 'Maria']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['first_name' => 'Maryam']));

    // Muss das vorhandene Mitglied gefunden haben (nicht 'new')
    expect($result['existing_id'])->toBe($member->id);
    expect($result['status'])->not->toBe('new');
});

test('compare findet mitglied per name und geburtsdatum wenn keine passnummer', function () {
    makeExistingMember(['pass_number' => null]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['pass_number' => null]));

    // Fallback: Name + Geburtsdatum → unchanged
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare gibt new zurück wenn weder passnummer noch name+dob übereinstimmen', function () {
    makeExistingMember(['pass_number' => '9999-0000', 'last_name' => 'AnderesName']);

    $service = new MemberImportService();
    // Passnummer stimmt nicht, Name stimmt nicht → kein Match → new
    $result  = $service->compare(makeMemberData(['pass_number' => '0765-0056']));

    expect($result['status'])->toBe('new');
});

// ── compare(): Geändert ───────────────────────────────────────────────────────

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
    // Reproduziert den Bug: $existing->date_of_birth ist ein Carbon-Objekt.
    // Falsche Normalisierung via (string) Carbon würde "2012-09-08 00:00:00" != "2012-09-08" ergeben.
    makeExistingMember(['date_of_birth' => '2012-09-08']);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['date_of_birth' => '2012-09-08']));

    expect($result['diff'])->not->toHaveKey('date_of_birth');
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare normalisiert boolean eligible_to_play korrekt', function () {
    // Bug: (string) true = '1', (string) false = '' (nicht '0').
    // Falsches Casting könnte true vs true als unterschiedlich markieren.
    makeExistingMember(['eligible_to_play' => true]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['eligible_to_play' => true]));

    expect($result['diff'])->not->toHaveKey('eligible_to_play');
    expect($result['diff'])->toBe([]);
    expect($result['status'])->toBe('unchanged');
});

test('compare erkennt changed eligible_to_play korrekt', function () {
    makeExistingMember(['eligible_to_play' => true]);

    $service = new MemberImportService();
    $result  = $service->compare(makeMemberData(['eligible_to_play' => false]));

    expect($result['status'])->toBe('changed');
    expect($result['diff'])->toHaveKey('eligible_to_play');
});

// ── execute(): Import ausführen ───────────────────────────────────────────────

test('execute legt neuen member an und schreibt import-log', function () {
    $user    = createPlainUser();
    $service = new MemberImportService();

    $processedRows = [
        0 => [
            'mapped'        => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'date_of_birth' => '2012-09-08', 'gender' => 'female', 'pass_number' => '0765-0056', 'eligible_to_play' => true, 'status' => 'active'],
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
            'mapped'        => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'date_of_birth' => '2012-09-08', 'gender' => 'female', 'pass_number' => '0765-0056', 'eligible_to_play' => true, 'status' => 'active'],
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
        0 => ['mapped' => ['first_name' => 'A', 'last_name' => 'B', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play' => true, 'status' => 'active'], 'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => []],
        1 => ['mapped' => ['first_name' => 'C', 'last_name' => 'D', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play' => true, 'status' => 'active'], 'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => []],
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

    // Zweiter Eintrag hat ungültige existing_id → findOrFail wirft ModelNotFoundException
    $processedRows = [
        0 => ['mapped' => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play' => true, 'status' => 'active'], 'custom_fields' => [], 'status' => 'new', 'existing_id' => null, 'diff' => []],
        1 => ['mapped' => ['first_name' => 'X', 'last_name' => 'Y', 'date_of_birth' => null, 'gender' => null, 'pass_number' => null, 'eligible_to_play' => true, 'status' => 'active'], 'custom_fields' => [], 'status' => 'changed', 'existing_id' => 99999, 'diff' => ['gender' => ['old' => 'male', 'new' => 'female']]],
    ];

    // Pest: toThrow() erwartet eine Closure die die Exception wirft
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

    // Dank Transaktion: kein Member angelegt, kein Log
    expect(Member::where('last_name', 'Akhabach')->exists())->toBeFalse();
    expect(MemberImportLog::where('source', 'dfbnet')->exists())->toBeFalse();
});
