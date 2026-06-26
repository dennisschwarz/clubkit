<?php

use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;

// ── Auth-Schutz ────────────────────────────────────────────────────────────────

test('gast wird bei GET /management auf login weitergeleitet', function () {
    $this->get('/management')->assertRedirect('/login');
});

test('gast wird bei POST /management/functions auf login weitergeleitet', function () {
    $this->post('/management/functions')->assertRedirect('/login');
});

test('gast wird bei POST /management/tasks auf login weitergeleitet', function () {
    $this->post('/management/tasks')->assertRedirect('/login');
});

// ── Permission-Schutz ──────────────────────────────────────────────────────────

test('user ohne permission kann GET /management nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/management')->assertStatus(403);
});

test('user ohne permission kann keine Funktion anlegen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/management/functions')->assertStatus(403);
});

test('user ohne permission kann keine Aufgabe anlegen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/management/tasks')->assertStatus(403);
});

test('user mit nur management.view kann keine Funktion anlegen', function () {
    $user = createUserWithPermission('management.view');
    $this->actingAs($user)->post('/management/functions', [
        'name' => 'Trainer',
    ])->assertStatus(403);
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('user mit management.view sieht Organisations-Übersicht', function () {
    $user = createUserWithPermission('management.view');
    $this->actingAs($user)->get('/management')->assertStatus(200);
});

// ── Funktionen: Store ──────────────────────────────────────────────────────────

test('user mit management.functions.manage kann Funktion anlegen', function () {
    $user = createUserWithPermission('management.functions.manage');

    $this->actingAs($user)->post('/management/functions', [
        'name' => 'Kassenwart',
    ])->assertRedirect('/management');

    $this->assertDatabaseHas('management_functions', ['name' => 'Kassenwart']);
});

test('store Funktion gibt 422 bei fehlendem Namen zurück', function () {
    $user = createUserWithPermission('management.functions.manage');
    $this->actingAs($user)->post('/management/functions', [])->assertSessionHasErrors('name');
});

test('store Funktion gibt 422 bei zu langem Namen zurück', function () {
    $user = createUserWithPermission('management.functions.manage');
    $this->actingAs($user)->post('/management/functions', [
        'name' => str_repeat('X', 101),
    ])->assertSessionHasErrors('name');
});

// ── Funktionen: Update ─────────────────────────────────────────────────────────

test('user mit management.functions.manage kann Funktion umbenennen', function () {
    $fn   = ManagementFunction::factory()->create(['name' => 'Alt']);
    $user = createUserWithPermission('management.functions.manage');

    $this->actingAs($user)->patch('/management/functions/' . $fn->id, [
        'name' => 'Trainer',
    ])->assertRedirect('/management');

    $this->assertDatabaseHas('management_functions', ['id' => $fn->id, 'name' => 'Trainer']);
});

// ── Funktionen: Destroy ────────────────────────────────────────────────────────

test('user mit management.functions.manage kann Funktion löschen', function () {
    $fn   = ManagementFunction::factory()->create();
    $user = createUserWithPermission('management.functions.manage');

    $this->actingAs($user)->delete('/management/functions/' . $fn->id)
        ->assertRedirect('/management');

    $this->assertDatabaseMissing('management_functions', ['id' => $fn->id]);
});

// ── Aufgaben: Store ────────────────────────────────────────────────────────────

test('user mit management.tasks.manage kann Aufgabe anlegen', function () {
    $user = createUserWithPermission('management.tasks.manage');

    $this->actingAs($user)->post('/management/tasks', [
        'name'        => 'Platzpflege',
        'description' => 'Regelmäßige Pflege des Sportplatzes.',
    ])->assertRedirect();

    $this->assertDatabaseHas('management_tasks', ['name' => 'Platzpflege']);
});

test('store Aufgabe gibt 422 bei fehlendem Namen zurück', function () {
    $user = createUserWithPermission('management.tasks.manage');
    $this->actingAs($user)->post('/management/tasks', [])->assertSessionHasErrors('name');
});

// ── Aufgaben: Update ───────────────────────────────────────────────────────────

test('user mit management.tasks.manage kann Aufgabe aktualisieren', function () {
    $task = ManagementTask::factory()->create(['name' => 'Alt']);
    $user = createUserWithPermission('management.tasks.manage');

    $this->actingAs($user)->patch('/management/tasks/' . $task->id, [
        'name' => 'Materialwart',
    ])->assertRedirect();

    $this->assertDatabaseHas('management_tasks', ['id' => $task->id, 'name' => 'Materialwart']);
});

// ── Aufgaben: Destroy ──────────────────────────────────────────────────────────

test('user mit management.tasks.manage kann Aufgabe löschen', function () {
    $task = ManagementTask::factory()->create();
    $user = createUserWithPermission('management.tasks.manage');

    $this->actingAs($user)->delete('/management/tasks/' . $task->id)
        ->assertRedirect();

    $this->assertDatabaseMissing('management_tasks', ['id' => $task->id]);
});

test('user mit management.functions.manage aber ohne tasks.manage kann keine Aufgabe löschen', function () {
    $task = ManagementTask::factory()->create();
    $user = createUserWithPermission('management.functions.manage');

    $this->actingAs($user)->delete('/management/tasks/' . $task->id)->assertStatus(403);
    $this->assertDatabaseHas('management_tasks', ['id' => $task->id]);
});
