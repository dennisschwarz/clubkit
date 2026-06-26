<?php

use Illuminate\Http\UploadedFile;
use Modules\Import\Models\ImportSession;
use Modules\Import\Models\MemberImportLog;
use Modules\Members\Models\Member;

// ── Hilfsfunktion: DFBnet-CSV als UploadedFile ────────────────────────────────

function dfbnetUploadedFile(): UploadedFile
{
    $csv = implode("\n", [
        'Name Künstlername;Vorname Rufname;Geb.;Nat.;A;VS;Passnummer;Spielrecht ab;Reg. am',
        'Akhabach ;Maryam (w) ;08.09.2012;D;X;;0765-0056;P 08.07.2025 F 08.07.2025;26.11.2025',
        'Mueller ;Anna (w) ;01.03.2014;D;X;;0765-0042;P 15.07.2025 F 15.07.2025;01.01.2026',
    ]);

    // Windows-1252 enkodieren wie ein echter DFBnet-Export
    $encoded = mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');

    $tmpFile = tempnam(sys_get_temp_dir(), 'dfbnet_test_') . '.csv';
    file_put_contents($tmpFile, $encoded);

    return new UploadedFile(
        path:          $tmpFile,
        originalName:  'HSV_Langenfeld-Test.csv',
        mimeType:      'text/csv',
        error:         UPLOAD_ERR_OK,
        test:          true,
    );
}

// Hilfsfunktion: fertigen Mapping-POST simulieren
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

// ── Auth-Schutz ───────────────────────────────────────────────────────────────

test('gast wird bei GET /mitglieder/import auf login weitergeleitet', function () {
    $this->get('/mitglieder/import')->assertRedirect('/login');
});

test('gast wird bei POST /mitglieder/import/upload auf login weitergeleitet', function () {
    $this->post('/mitglieder/import/upload')->assertRedirect('/login');
});

// ── Permission-Schutz ─────────────────────────────────────────────────────────

test('user ohne import.view kann GET /mitglieder/import nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/mitglieder/import')->assertStatus(403);
});

test('user ohne import.execute kann POST /upload nicht aufrufen', function () {
    $user = createUserWithPermission('import.view');
    $this->actingAs($user)
         ->post('/mitglieder/import/upload')
         ->assertStatus(403);
});

// ── Stufe 1: Upload ───────────────────────────────────────────────────────────

test('berechtigter user sieht upload-formular', function () {
    $user = createUserWithPermission('import.view', 'import.execute');
    $this->actingAs($user)
         ->get('/mitglieder/import')
         ->assertStatus(200)
         ->assertSee('CSV-Datei');
});

test('upload ohne datei gibt validierungsfehler', function () {
    $user = createUserWithPermission('import.view', 'import.execute');
    $this->actingAs($user)
         ->post('/mitglieder/import/upload', [])
         ->assertSessionHasErrors('csv_file');
});

test('upload einer gültigen dfbnet-csv erstellt import-session und leitet zu mapping weiter', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    $response = $this->actingAs($user)
                     ->post('/mitglieder/import/upload', [
                         'csv_file' => dfbnetUploadedFile(),
                     ]);

    $session = ImportSession::where('created_by', $user->id)->first();

    expect($session)->not->toBeNull();
    expect($session->source)->toBe('dfbnet');
    expect($session->filename)->toBe('HSV_Langenfeld-Test.csv');
    expect($session->column_headers)->toHaveCount(9);
    expect($session->raw_rows)->toHaveCount(2);

    $response->assertRedirect(route('import.mapping', $session->id));
});

test('upload einer nicht erkannten datei gibt fehlermeldung', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    $file = UploadedFile::fake()->createWithContent(
        'unbekannt.csv',
        "Spalte1;Spalte2;Spalte3\nWert1;Wert2;Wert3\n"
    );

    $this->actingAs($user)
         ->post('/mitglieder/import/upload', ['csv_file' => $file])
         ->assertSessionHasErrors('csv_file');
});

// ── Stufe 2: Mapping ──────────────────────────────────────────────────────────

test('mapping-formular wird für gültige session angezeigt', function () {
    $user    = createUserWithPermission('import.view', 'import.execute');
    $session = ImportSession::create([
        'created_by'     => $user->id,
        'source'         => 'dfbnet',
        'filename'       => 'test.csv',
        'column_headers' => ['Name Künstlername', 'Vorname Rufname', 'Geb.', 'Passnummer'],
        'raw_rows'       => [['Müller', 'Anna (w)', '01.01.2010', '1234-5678']],
        'samples'        => ['Name Künstlername' => ['Müller']],
        'expires_at'     => now()->addHours(2),
    ]);

    $this->actingAs($user)
         ->get(route('import.mapping', $session->id))
         ->assertStatus(200)
         ->assertSee('Name Künstlername')
         ->assertSee('Spalten zuordnen');
});

test('abgelaufene session leitet zu upload zurück', function () {
    $user    = createUserWithPermission('import.view', 'import.execute');
    $session = ImportSession::create([
        'created_by'     => $user->id,
        'source'         => 'dfbnet',
        'filename'       => 'test.csv',
        'column_headers' => ['Name'],
        'raw_rows'       => [['Müller']],
        'samples'        => [],
        'expires_at'     => now()->subHour(), // abgelaufen
    ]);

    $this->actingAs($user)
         ->get(route('import.mapping', $session->id))
         ->assertRedirect(route('import.index'))
         ->assertSessionHasErrors('session');
});

test('mapping speichern verarbeitet zeilen und leitet zu preview weiter', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    // Zuerst Upload
    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);

    $session = ImportSession::where('created_by', $user->id)->first();

    // Mapping speichern
    $response = $this->actingAs($user)
                     ->post(route('import.mapping.save', $session->id), [
                         'mapping' => defaultMapping(),
                     ]);

    $session->refresh();
    expect($session->mapping)->not->toBeNull();
    expect($session->processed_rows)->not->toBeNull();
    expect($session->processed_rows)->toHaveCount(2);

    $response->assertRedirect(route('import.preview', $session->id));
});

// ── Stufe 3: Vorschau ─────────────────────────────────────────────────────────

test('vorschau zeigt korrekte status-zusammenfassung', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    // Session mit verarbeiteten Zeilen direkt anlegen
    $session = ImportSession::create([
        'created_by'    => $user->id,
        'source'        => 'dfbnet',
        'filename'      => 'test.csv',
        'column_headers'=> ['Vorname', 'Nachname'],
        'raw_rows'      => [],
        'samples'       => [],
        'mapping'       => ['Vorname' => 'first_name', 'Nachname' => 'last_name'],
        'processed_rows'=> [
            0 => ['mapped' => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'pass_number' => '0765-0056'], 'status' => 'new',       'existing_id' => null, 'diff' => [], 'custom_fields' => []],
            1 => ['mapped' => ['first_name' => 'Anna',   'last_name' => 'Mueller',   'pass_number' => '0765-0042'], 'status' => 'unchanged', 'existing_id' => 1,    'diff' => [], 'custom_fields' => []],
        ],
        'expires_at'    => now()->addHours(2),
    ]);

    $this->actingAs($user)
         ->get(route('import.preview', $session->id))
         ->assertStatus(200)
         ->assertSee('1') // 1 neu
         ->assertSee('NEU');
});

// ── Finaler Import ────────────────────────────────────────────────────────────

test('execute importiert neue mitglieder und legt import-log an', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    // Upload + Mapping
    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);
    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);
    $session->refresh();

    // Alle Indizes auswählen
    $indexes = array_keys($session->processed_rows);
    $newIndexes = array_filter($indexes, fn ($i) => $session->processed_rows[$i]['status'] === 'new');

    $this->actingAs($user)
         ->post(route('import.execute', $session->id), [
             'selected' => $newIndexes,
         ])
         ->assertRedirect(route('members.index'));

    // Import-Log muss existieren
    $this->assertDatabaseHas('member_import_logs', [
        'source'     => 'dfbnet',
        'created_by' => $user->id,
    ]);

    // Mitglieder müssen angelegt worden sein
    $this->assertDatabaseHas('members', ['last_name' => 'Akhabach', 'first_name' => 'Maryam']);
    $this->assertDatabaseHas('members', ['last_name' => 'Mueller',  'first_name' => 'Anna']);

    // Session muss gelöscht worden sein
    expect(ImportSession::find($session->id))->toBeNull();
});

test('execute ohne auswahl gibt validierungsfehler', function () {
    $user    = createUserWithPermission('import.view', 'import.execute');
    $session = ImportSession::create([
        'created_by'    => $user->id,
        'source'        => 'dfbnet',
        'filename'      => 'test.csv',
        'column_headers'=> [],
        'raw_rows'      => [],
        'samples'       => [],
        'processed_rows'=> [['mapped' => [], 'status' => 'new', 'existing_id' => null, 'diff' => [], 'custom_fields' => []]],
        'expires_at'    => now()->addHours(2),
    ]);

    $this->actingAs($user)
         ->post(route('import.execute', $session->id), ['selected' => []])
         ->assertSessionHasErrors('selected');
});

// ── Abbrechen ─────────────────────────────────────────────────────────────────

test('abbrechen löscht session und leitet zu mitgliedern weiter', function () {
    $user    = createUserWithPermission('import.view', 'import.execute');
    $session = ImportSession::create([
        'created_by'    => $user->id,
        'source'        => 'dfbnet',
        'filename'      => 'test.csv',
        'column_headers'=> [],
        'raw_rows'      => [],
        'samples'       => [],
        'expires_at'    => now()->addHours(2),
    ]);

    $this->actingAs($user)
         ->post(route('import.cancel', $session->id))
         ->assertRedirect(route('members.index'));

    expect(ImportSession::find($session->id))->toBeNull();
});

// ── Duplikat-Erkennung ────────────────────────────────────────────────────────

test('bestehender member wird als unchanged erkannt', function () {
    $user = createUserWithPermission('import.view', 'import.execute');

    // Member anlegen der genau mit dem CSV übereinstimmt
    Member::create([
        'first_name'       => 'Maryam',
        'last_name'        => 'Akhabach',
        'date_of_birth'    => '2012-09-08',
        'gender'           => 'female',
        'pass_number'      => '0765-0056',
        'eligible_to_play' => true,
        'status'           => 'active',
        'created_by'       => $user->id,
    ]);

    $this->actingAs($user)->post('/mitglieder/import/upload', [
        'csv_file' => dfbnetUploadedFile(),
    ]);
    $session = ImportSession::where('created_by', $user->id)->first();

    $this->actingAs($user)->post(route('import.mapping.save', $session->id), [
        'mapping' => defaultMapping(),
    ]);
    $session->refresh();

    // Erster Datensatz (Akhabach) muss als 'unchanged' erkannt werden
    expect($session->processed_rows[0]['status'])->toBe('unchanged');
    // Zweiter Datensatz (Mueller) muss als 'new' erkannt werden
    expect($session->processed_rows[1]['status'])->toBe('new');
});
