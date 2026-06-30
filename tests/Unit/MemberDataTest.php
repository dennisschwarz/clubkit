<?php

declare(strict_types=1);

use Modules\Import\MemberData;

uses(Tests\TestCase::class);

// ── Constructor & defaults ────────────────────────────────────────────────────

test('memberdto speichert pflichtfelder korrekt', function () {
    $dto = new MemberData(
        first_name:    'Maryam',
        last_name:     'Akhabach',
        date_of_birth: '2012-09-08',
        gender:        'female',
        pass_number:   '0765-0056',
    );

    expect($dto->first_name)->toBe('Maryam');
    expect($dto->last_name)->toBe('Akhabach');
    expect($dto->date_of_birth)->toBe('2012-09-08');
    expect($dto->gender)->toBe('female');
    expect($dto->pass_number)->toBe('0765-0056');
});

test('memberdto hat korrekte standardwerte', function () {
    $dto = new MemberData(
        first_name:    'Test',
        last_name:     'User',
        date_of_birth: null,
        gender:        null,
        pass_number:   null,
    );

    // eligible_to_play_date is null by default (no date → not eligible)
    expect($dto->eligible_to_play_date)->toBeNull();
    expect($dto->status)->toBe('active');
    expect($dto->custom_fields)->toBe([]);
});

test('memberdto akzeptiert spielberechtigt-datum', function () {
    $dto = new MemberData(
        first_name:            'Test',
        last_name:             'User',
        date_of_birth:         null,
        gender:                null,
        pass_number:           null,
        eligible_to_play_date: '2025-07-01',
    );

    expect($dto->eligible_to_play_date)->toBe('2025-07-01');
});

test('memberdto akzeptiert null als spielberechtigt-datum (nicht spielberechtigt)', function () {
    $dto = new MemberData(
        first_name:            'Test',
        last_name:             'User',
        date_of_birth:         null,
        gender:                null,
        pass_number:           null,
        eligible_to_play_date: null,
    );

    expect($dto->eligible_to_play_date)->toBeNull();
});

test('memberdto akzeptiert custom fields', function () {
    $dto = new MemberData(
        first_name:    'Test',
        last_name:     'User',
        date_of_birth: null,
        gender:        null,
        pass_number:   null,
        custom_fields: ['nationality' => 'D', 'club_number' => '42'],
    );

    expect($dto->custom_fields)->toBe(['nationality' => 'D', 'club_number' => '42']);
});

test('memberdto ist readonly', function () {
    $dto = new MemberData(
        first_name:    'Test',
        last_name:     'User',
        date_of_birth: null,
        gender:        null,
        pass_number:   null,
    );

    expect(fn () => $dto->first_name = 'Geändert')
        ->toThrow(Error::class);
});
