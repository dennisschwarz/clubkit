<?php

use Modules\Import\Importers\DfbNetImporter;
use Modules\Import\MemberData;

uses(Tests\TestCase::class);

// ── Hilfsfunktion: DFBnet-CSV als UTF-8 String ──────────────────────────────

function dfbnetCsvUtf8(): string
{
    return implode("\n", [
        'Name Künstlername;Vorname Rufname;Geb.;Nat.;A;VS;Passnummer;Spielrecht ab;Reg. am',
        'Akhabach ;Maryam (w) ;08.09.2012;D;X;;0765-0056;P 08.07.2025 F 08.07.2025;26.11.2025',
        'Müller ;Anna Maria (w) ;01.03.2014;D;X;;0765-0042;P 15.07.2025 F 15.07.2025;01.01.2026',
        'Yilmaz ;Zeynep (w) ;22.11.2013;D;X;;;P 20.08.2025 F 20.08.2025;15.03.2026',
    ]) . "\n";
}

// Windows-1252 enkodierter CSV-String für Encoding-Tests
function dfbnetCsvWindows1252(): string
{
    return mb_convert_encoding(dfbnetCsvUtf8(), 'Windows-1252', 'UTF-8');
}

// ── canHandle ─────────────────────────────────────────────────────────────────

test('canHandle gibt true zurück für dfbnet-csv', function () {
    $importer  = new DfbNetImporter();
    $firstLine = 'Name Künstlername;Vorname Rufname;Geb.;Nat.;A;VS;Passnummer;Spielrecht ab;Reg. am';

    expect($importer->canHandle('HSV_Langenfeld.csv', $firstLine))->toBeTrue();
});

test('canHandle gibt false zurück wenn Passnummer fehlt', function () {
    $importer  = new DfbNetImporter();
    $firstLine = 'Name;Vorname;Geburtsdatum;Verein';

    expect($importer->canHandle('andere_datei.csv', $firstLine))->toBeFalse();
});

test('canHandle gibt false zurück wenn Spielrecht fehlt', function () {
    $importer  = new DfbNetImporter();
    $firstLine = 'Name;Passnummer;Verein';

    expect($importer->canHandle('datei.csv', $firstLine))->toBeFalse();
});

// ── getSourceName ─────────────────────────────────────────────────────────────

test('getSourceName gibt dfbnet zurück', function () {
    $importer = new DfbNetImporter();
    expect($importer->getSourceName())->toBe('dfbnet');
});

// ── getColumnHeaders ──────────────────────────────────────────────────────────

test('getColumnHeaders liest spaltenköpfe korrekt aus', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());

    expect($headers)->toHaveCount(9);
    expect($headers[0])->toBe('Name Künstlername');
    expect($headers[6])->toBe('Passnummer');
});

// ── getRawRows ────────────────────────────────────────────────────────────────

test('getRawRows liest datensätze aus und überspringt header', function () {
    $importer = new DfbNetImporter();
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());

    expect($rows)->toHaveCount(3);
});

test('getRawRows konvertiert windows-1252 encoding zu utf-8', function () {
    $importer = new DfbNetImporter();
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());

    // 'Müller' enthält Umlaut – muss korrekt als UTF-8 ankommen
    expect($rows[1][0])->toBe('Müller');
});

test('getRawRows trimmt leerzeichen aus spalten', function () {
    $importer = new DfbNetImporter();
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());

    // 'Akhabach ' hat trailing space im Original
    expect($rows[0][0])->toBe('Akhabach');
});

test('getRawRows ignoriert leere zeilen', function () {
    $csv = dfbnetCsvUtf8() . "\n\n   \n";
    // Windows-1252 Konvertierung für diesen Test ggf. überspringen
    $importer = new DfbNetImporter();
    $rows     = $importer->getRawRows(mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

    expect($rows)->toHaveCount(3);
});

// ── getSuggestedMapping ───────────────────────────────────────────────────────

test('getSuggestedMapping enthält korrekte zuordnungen', function () {
    $importer = new DfbNetImporter();
    $mapping  = $importer->getSuggestedMapping();

    expect($mapping['Name Künstlername'])->toBe('last_name');
    expect($mapping['Vorname Rufname'])->toBe('first_name');
    expect($mapping['Geb.'])->toBe('date_of_birth');
    expect($mapping['Passnummer'])->toBe('pass_number');
    expect($mapping['Nat.'])->toBe('skip');
    expect($mapping['VS'])->toBe('skip');
});

// ── applyMapping ──────────────────────────────────────────────────────────────

test('applyMapping erstellt korrektes memberdto', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());
    $mapping  = $importer->getSuggestedMapping();

    $dto = $importer->applyMapping($rows[0], $headers, $mapping);

    expect($dto)->toBeInstanceOf(MemberData::class);
    expect($dto->last_name)->toBe('Akhabach');
    expect($dto->first_name)->toBe('Maryam');
    expect($dto->date_of_birth)->toBe('2012-09-08');
    expect($dto->gender)->toBe('female');
    expect($dto->pass_number)->toBe('0765-0056');
    expect($dto->eligible_to_play)->toBeTrue();
    expect($dto->status)->toBe('active');
});

test('applyMapping erkennt geschlecht (w) korrekt', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());
    $mapping  = $importer->getSuggestedMapping();

    $dto = $importer->applyMapping($rows[0], $headers, $mapping);
    expect($dto->gender)->toBe('female');
});

test('applyMapping entfernt rufname-klammer aus vorname', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());
    $mapping  = $importer->getSuggestedMapping();

    // Zeile 1: 'Anna Maria (w)' → 'Anna Maria'
    $dto = $importer->applyMapping($rows[1], $headers, $mapping);
    expect($dto->first_name)->toBe('Anna Maria');
});

test('applyMapping konvertiert datum von dd.mm.yyyy zu yyyy-mm-dd', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());
    $mapping  = $importer->getSuggestedMapping();

    $dto = $importer->applyMapping($rows[1], $headers, $mapping);
    expect($dto->date_of_birth)->toBe('2014-03-01');
});

test('applyMapping setzt pass_number auf null wenn leer', function () {
    $importer = new DfbNetImporter();
    $headers  = $importer->getColumnHeaders(dfbnetCsvWindows1252());
    $rows     = $importer->getRawRows(dfbnetCsvWindows1252());
    $mapping  = $importer->getSuggestedMapping();

    // Zeile 2: Yilmaz hat keine Passnummer
    $dto = $importer->applyMapping($rows[2], $headers, $mapping);
    expect($dto->pass_number)->toBeNull();
});

test('applyMapping überspringt spalten mit skip-mapping', function () {
    $importer = new DfbNetImporter();
    $headers  = ['Name Künstlername', 'Vorname Rufname', 'Geb.', 'Passnummer'];
    $rawRow   = ['Muster', 'Max (m)', '15.05.2010', '1234-5678'];
    $mapping  = [
        'Name Künstlername' => 'last_name',
        'Vorname Rufname'   => 'first_name',
        'Geb.'              => 'skip',        // Datum überspringen
        'Passnummer'        => 'pass_number',
    ];

    $dto = $importer->applyMapping($rawRow, $headers, $mapping);

    expect($dto->last_name)->toBe('Muster');
    expect($dto->first_name)->toBe('Max');
    expect($dto->date_of_birth)->toBeNull();  // übersprungen
    expect($dto->pass_number)->toBe('1234-5678');
    expect($dto->gender)->toBe('male');
});
