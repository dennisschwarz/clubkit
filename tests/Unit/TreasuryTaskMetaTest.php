<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Management\Models\ManagementTask;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryTaskMeta;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a treasury task meta record can be created linking a task to an account', function () {
    $task    = ManagementTask::factory()->create();
    $account = TreasuryAccount::factory()->create();

    $meta = TreasuryTaskMeta::factory()->create([
        'task_id'        => $task->id,
        'account_id'     => $account->id,
        'default_amount' => 120.00,
    ]);

    expect($meta->task_id)->toBe($task->id)
        ->and($meta->account_id)->toBe($account->id)
        ->and((float) $meta->default_amount)->toBe(120.0);
});

test('a task can only be linked to one account (unique constraint)', function () {
    $task    = ManagementTask::factory()->create();
    $account = TreasuryAccount::factory()->create();

    TreasuryTaskMeta::factory()->create(['task_id' => $task->id, 'account_id' => $account->id]);

    expect(fn () => TreasuryTaskMeta::factory()->create([
        'task_id'    => $task->id,
        'account_id' => $account->id,
    ]))->toThrow(\Exception::class);
});

test('task meta belongs to its management task', function () {
    $task = ManagementTask::factory()->create(['name' => 'Jahresbeitrag 2026']);
    $meta = TreasuryTaskMeta::factory()->create(['task_id' => $task->id]);

    expect($meta->task->name)->toBe('Jahresbeitrag 2026');
});

test('task meta belongs to its treasury account', function () {
    $account = TreasuryAccount::factory()->create(['name' => 'Jugendkasse']);
    $meta    = TreasuryTaskMeta::factory()->create(['account_id' => $account->id]);

    expect($meta->account->name)->toBe('Jugendkasse');
});

test('task meta has an optional due date', function () {
    $meta = TreasuryTaskMeta::factory()->create(['due_date' => null]);

    expect($meta->due_date)->toBeNull();
});
