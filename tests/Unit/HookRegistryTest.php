<?php

declare(strict_types=1);

use Modules\Core\Services\HookRegistry;

// ── Empty states ──────────────────────────────────────────────────────────────

test('leerer Extension Point gibt leeres Array zurück', function () {
    $registry = new HookRegistry();

    expect($registry->get('nicht.vorhanden'))->toBe([]);
});

test('has() gibt false zurück wenn kein Hook registriert', function () {
    $registry = new HookRegistry();

    expect($registry->has('member.modal.tabs'))->toBeFalse();
});

// ── Registration ──────────────────────────────────────────────────────────────

test('has() gibt true zurück nach Registrierung', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'some::view');

    expect($registry->has('member.modal.tabs'))->toBeTrue();
});

test('registrierter Hook wird in get() zurückgegeben', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'youth-club-mode::member-modal-tab');

    expect($registry->get('member.modal.tabs'))
        ->toBe(['youth-club-mode::member-modal-tab']);
});

// ── Priority sorting ──────────────────────────────────────────────────────────

test('hooks werden nach Priorität aufsteigend sortiert', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'view-b', 20);
    $registry->register('member.modal.tabs', 'view-a', 10);
    $registry->register('member.modal.tabs', 'view-c', 30);

    expect($registry->get('member.modal.tabs'))
        ->toBe(['view-a', 'view-b', 'view-c']);
});

test('standard-Priorität ist 10', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'default-view');        // priority 10
    $registry->register('member.modal.tabs', 'high-prio-view', 5);  // priority 5

    expect($registry->get('member.modal.tabs'))
        ->toBe(['high-prio-view', 'default-view']);
});

test('gleiche Priorität behält Eintragsreihenfolge', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'view-x', 10);
    $registry->register('member.modal.tabs', 'view-y', 10);

    expect($registry->get('member.modal.tabs'))
        ->toBe(['view-x', 'view-y']);
});

// ── Isolation between extension points ───────────────────────────────────────

test('verschiedene Extension Points beeinflussen sich nicht', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs',     'tab-view');
    $registry->register('member.modal.sections', 'section-view');
    $registry->register('member.page.scripts',   'script-view');

    expect($registry->get('member.modal.tabs'))    ->toBe(['tab-view']);
    expect($registry->get('member.modal.sections'))->toBe(['section-view']);
    expect($registry->get('member.page.scripts'))  ->toBe(['script-view']);
});

test('has() gibt false für anderen Extension Point', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'some::view');

    expect($registry->has('member.modal.sections'))->toBeFalse();
});

// ── Multiple hooks on the same point ─────────────────────────────────────────

test('mehrere Module können sich an denselben Point hängen', function () {
    $registry = new HookRegistry();
    $registry->register('member.modal.tabs', 'module-a::tab', 10);
    $registry->register('member.modal.tabs', 'module-b::tab', 20);
    $registry->register('member.modal.tabs', 'module-c::tab', 30);

    expect($registry->get('member.modal.tabs'))->toHaveCount(3);
});
