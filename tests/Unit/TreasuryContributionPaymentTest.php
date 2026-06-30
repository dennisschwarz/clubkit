<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Members\Models\Member;
use Modules\Management\Models\ManagementTask;
use Modules\Treasury\Models\TreasuryContributionPayment;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a contribution payment entry can be created as unpaid', function () {
    $payment = TreasuryContributionPayment::factory()->create([
        'amount'  => 80.00,
        'paid_at' => null,
    ]);

    expect($payment->isPaid())->toBeFalse()
        ->and((float) $payment->amount)->toBe(80.0);
});

test('a contribution payment entry can be created as paid', function () {
    $payment = TreasuryContributionPayment::factory()->paid()->create();

    expect($payment->isPaid())->toBeTrue()
        ->and($payment->paid_at)->not->toBeNull();
});

test('isPaid returns false when paid_at is null', function () {
    $payment = TreasuryContributionPayment::factory()->create(['paid_at' => null]);

    expect($payment->isPaid())->toBeFalse();
});

test('isPaid returns true when paid_at is set', function () {
    $payment = TreasuryContributionPayment::factory()->paid()->create();

    expect($payment->isPaid())->toBeTrue();
});

test('a payment belongs to a member', function () {
    $member  = Member::factory()->create(['first_name' => 'Max', 'last_name' => 'Muster']);
    $payment = TreasuryContributionPayment::factory()->create(['member_id' => $member->id]);

    expect($payment->member->last_name)->toBe('Muster');
});

test('a payment belongs to a task', function () {
    $task    = ManagementTask::factory()->create(['name' => 'Spielkleidung']);
    $payment = TreasuryContributionPayment::factory()->create(['task_id' => $task->id]);

    expect($payment->task->name)->toBe('Spielkleidung');
});

test('unique constraint prevents two entries for the same task and member', function () {
    $task   = ManagementTask::factory()->create();
    $member = Member::factory()->create();

    TreasuryContributionPayment::factory()->create([
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]);

    expect(fn () => TreasuryContributionPayment::factory()->create([
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]))->toThrow(\Exception::class);
});
