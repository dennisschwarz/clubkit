<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Import\Models\ImportSession;
use Modules\Members\Models\Member;

// ── beforeEach: install required modules ─────────────────────────────────────

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',    'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members', 'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'import',  'is_active' => 1, 'installed_at' => now()],
    ]);
});

// ── Helper functions ──────────────────────────────────────────────────────────

/**
 * Create an UploadedFile from the DFBnet fixture CSV.
 *
 * Uses the fixture file at tests/Fixtures/dfbnet_sample.csv.
 * DfbNetImporter::decode() automatically handles both Windows-1252 and UTF-8
 * input via mb_check_encoding(), so the file encoding on disk does not matter.
 *
 * @return UploadedFile
 */
function dfbnetUploadedFile(): UploadedFile
{
    return new UploadedFile(
        path: base_path('tests/Fixtures/dfbnet_sample.csv'),
        originalName: 'dfbnet_sample.csv',
        mimeType: 'text/csv',
        error: UPLOAD_ERR_OK,
        test: true
    );
}

/**
 * Default column mapping for the DFBNet CSV fixture.
 *
 * Spielrecht ab → skip: the MemberData DTO will have eligible_to_play_date = null.
 * compare() compares all 7 fields; to detect a member as 'unchanged' the DB record
 * must also have eligible_to_play_date = null (not a date string).
 *
 * @return array<string, string>
 */
function defaultMapping(): array
{
    return [
        'Name Künstlername' => 'last_name',
        'Vorname Rufname'   => 'first_name',
        'Geb.'              => 'date_of_birth',
        'Nat.'              => 'skip',
        'A'                 => 'skip',
        'VS'                => 'skip',
        'Passnummer'        => 'pass_number',
        'Spielrecht ab'     => 'skip',
        'Reg. am'           => 'skip',
    ];
}

// ── Upload ────────────────────────────────────────────────────────────────────

test('upload requires import.view permission', function () {
    $user = createPlainUser();

    $this->actingAs($user)
        ->post('/mitglieder/import/upload', ['csv_file' => dfbnetUploadedFile()])
        ->assertForbidden();
});

test('upload creates an ImportSession', function () {
    $user = createUserWithPermission('import.view');

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);

    expect(ImportSession::where('created_by', $user->id)->exists())->toBeTrue();
});

test('upload parses all CSV rows into ImportSession', function () {
    $user = createUserWithPermission('import.view');

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);

    $session = ImportSession::where('created_by', $user->id)->first();

    expect($session)->not->toBeNull();
    expect($session->raw_rows)->not->toBeEmpty();
});

// ── Mapping ───────────────────────────────────────────────────────────────────

test('mapping.save stores mapping in ImportSession', function () {
    $user = createUserWithPermission('import.view');

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);

    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);

    $session->refresh();

    // ImportSession stores the column mapping under the 'mapping' field (not 'column_mapping')
    expect($session->mapping)->not->toBeEmpty();
});

test('mapping.save produces processed_rows with status fields', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);

    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);

    $session->refresh();

    expect($session->processed_rows)->not->toBeEmpty();
    expect($session->processed_rows[0])->toHaveKey('status');
});

// ── Comparison ───────────────────────────────────────────────────────────────

test('neues mitglied wird als new erkannt', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);
    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);
    $session->refresh();

    // No existing members → all rows should be 'new'
    foreach ($session->processed_rows as $row) {
        expect($row['status'])->toBe('new');
    }
});

test('bestehender member wird als unchanged erkannt', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    // Create a member matching the CSV exactly.
    //
    // Field mapping notes:
    //   - first_name: 'Maryam' (stripped from 'Maryam (w)')
    //   - gender:     'female' (extracted from '(w)' marker by DfbNetImporter)
    //   - eligible_to_play_date: null — 'Spielrecht ab' is mapped to 'skip'
    //     so the DTO has eligible_to_play_date = null.
    //     MemberImportService::compare() checks all 7 fields; if the DB member
    //     has a date here but the DTO has null, it returns 'changed'.
    Member::create([
        'first_name'            => 'Maryam',
        'last_name'             => 'Akhabach',
        'date_of_birth'         => '2012-09-08',
        'gender'                => 'female',
        'pass_number'           => '0765-0056',
        'eligible_to_play_date' => null,
        'status'                => 'active',
        'created_by'            => $user->id,
    ]);

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);
    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);
    $session->refresh();

    // First row (Akhabach) must be detected as 'unchanged'
    expect($session->processed_rows[0]['status'])->toBe('unchanged');
    // Second row (Müller) must be detected as 'new'
    expect($session->processed_rows[1]['status'])->toBe('new');
});

// ── Execute ───────────────────────────────────────────────────────────────────

test('execute requires import.execute permission', function () {
    $user = createUserWithPermission('import.view');

    // Create a minimal ImportSession directly.
    // NOT NULL columns in migration (no default):
    //   source (string), filename (string), column_headers (json),
    //   raw_rows (json), samples (json), expires_at (timestamp).
    // Nullable columns: mapping, processed_rows.
    $session = ImportSession::create([
        'created_by'     => $user->id,
        'source'         => 'dfbnet',
        'filename'       => 'test.csv',
        'column_headers' => [],
        'raw_rows'       => [],
        'samples'        => [],
        'processed_rows' => [],
        'expires_at'     => now()->addHours(2),
    ]);

    $this->actingAs($user)
        ->post(route('import.execute', $session->id))
        ->assertForbidden();
});
