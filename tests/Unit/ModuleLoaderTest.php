<?php

// ModuleLoader nutzt base_path() – daher Laravel-Kontext nötig
uses(Tests\TestCase::class);

use App\Services\ModuleLoader;

// ── slugToFolder / modulePath ─────────────────────────────────────────────────

test('einfacher Slug ergibt korrekten Ordnernamen', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('members')))->toBe('Members');
});

test('Slug mit einem Bindestrich wird zu PascalCase', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('match-media')))->toBe('MatchMedia');
});

test('Slug mit zwei Bindestrichen wird zu PascalCase', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('youth-club-mode')))->toBe('YouthClubMode');
});

test('Core-Slug funktioniert korrekt', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('core')))->toBe('Core');
});

test('dreiteiliger Slug wird vollständig konvertiert', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('some-long-name')))->toBe('SomeLongName');
});

test('vierteiliger Slug wird vollständig konvertiert', function () {
    $loader = new ModuleLoader();

    expect(basename($loader->modulePath('a-b-c-d')))->toBe('ABCD');
});

// ── resolveDependencies: Grundfälle ──────────────────────────────────────────

test('einzelnes Modul ohne Abhängigkeiten wird direkt zurückgegeben', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core' => ['slug' => 'core', 'requires' => []],
    ];

    expect($loader->resolveDependencies(['core'], $available))->toBe(['core']);
});

test('Abhängigkeit kommt vor dem abhängigen Modul', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core'    => ['slug' => 'core',    'requires' => []],
        'members' => ['slug' => 'members', 'requires' => ['core']],
    ];

    $result = $loader->resolveDependencies(['members'], $available);

    expect($result)->toBe(['core', 'members']);
    expect(array_search('core', $result))->toBeLessThan(array_search('members', $result));
});

test('transitiv: drei Ebenen werden korrekt geordnet', function () {
    // seasons → teams → members → core
    $loader    = new ModuleLoader();
    $available = [
        'core'    => ['slug' => 'core',    'requires' => []],
        'members' => ['slug' => 'members', 'requires' => ['core']],
        'teams'   => ['slug' => 'teams',   'requires' => ['members']],
        'seasons' => ['slug' => 'seasons', 'requires' => ['teams']],
    ];

    $result = $loader->resolveDependencies(['seasons'], $available);

    // Jede Abhängigkeit muss vor ihrem Abhängigen stehen
    expect(array_search('core', $result))->toBeLessThan(array_search('members', $result));
    expect(array_search('members', $result))->toBeLessThan(array_search('teams', $result));
    expect(array_search('teams', $result))->toBeLessThan(array_search('seasons', $result));
    expect($result)->toHaveCount(4);
});

test('mehrere gewählte Module werden zusammen aufgelöst', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core'    => ['slug' => 'core',    'requires' => []],
        'members' => ['slug' => 'members', 'requires' => ['core']],
        'events'  => ['slug' => 'events',  'requires' => ['members']],
    ];

    $result = $loader->resolveDependencies(['members', 'events'], $available);

    expect($result)->toContain('core');
    expect($result)->toContain('members');
    expect($result)->toContain('events');
    expect(array_search('core', $result))->toBeLessThan(array_search('members', $result));
    expect(array_search('members', $result))->toBeLessThan(array_search('events', $result));
});

test('gemeinsame Abhängigkeiten werden nicht doppelt hinzugefügt', function () {
    // Sowohl 'members' als auch 'events' hängen von 'core' ab
    $loader    = new ModuleLoader();
    $available = [
        'core'    => ['slug' => 'core',    'requires' => []],
        'members' => ['slug' => 'members', 'requires' => ['core']],
        'events'  => ['slug' => 'events',  'requires' => ['core']],
    ];

    $result = $loader->resolveDependencies(['members', 'events'], $available);

    // 'core' darf nur einmal vorkommen
    expect(array_count_values($result)['core'])->toBe(1);
    expect($result)->toHaveCount(3);
});

test('Modul, das bereits in selected ist, wird nicht dupliziert', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core'    => ['slug' => 'core',    'requires' => []],
        'members' => ['slug' => 'members', 'requires' => ['core']],
    ];

    // 'core' ist explizit gewählt UND Abhängigkeit von 'members'
    $result = $loader->resolveDependencies(['core', 'members'], $available);

    expect(array_count_values($result)['core'])->toBe(1);
    expect($result)->toHaveCount(2);
});

// ── resolveDependencies: Fehlerfälle ─────────────────────────────────────────

test('unbekanntes Modul wirft RuntimeException', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core' => ['slug' => 'core', 'requires' => []],
    ];

    expect(fn () => $loader->resolveDependencies(['unknown-module'], $available))
        ->toThrow(\RuntimeException::class, "Modul 'unknown-module' ist nicht verfügbar");
});

test('unbekannte Abhängigkeit wirft RuntimeException', function () {
    $loader    = new ModuleLoader();
    $available = [
        'members' => ['slug' => 'members', 'requires' => ['core']], // 'core' fehlt in available
    ];

    expect(fn () => $loader->resolveDependencies(['members'], $available))
        ->toThrow(\RuntimeException::class, "Abhängigkeit 'core' für 'members' ist nicht verfügbar");
});

test('zyklische Abhängigkeit wird erkannt und wirft RuntimeException', function () {
    $loader    = new ModuleLoader();
    $available = [
        'a' => ['slug' => 'a', 'requires' => ['b']],
        'b' => ['slug' => 'b', 'requires' => ['a']], // Zyklus: a → b → a
    ];

    expect(fn () => $loader->resolveDependencies(['a'], $available))
        ->toThrow(\RuntimeException::class, 'Zyklische Abhängigkeit');
});

test('leere Auswahl gibt leeres Array zurück', function () {
    $loader    = new ModuleLoader();
    $available = [
        'core' => ['slug' => 'core', 'requires' => []],
    ];

    expect($loader->resolveDependencies([], $available))->toBe([]);
});
