<?php

declare(strict_types=1);

namespace Modules\Import\Importers;

use Modules\Import\ImporterInterface;
use Modules\Import\MemberData;

/**
 * Importer für DFBnet CSV-Exporte.
 *
 * Typisches Format:
 *   Name Künstlername;Vorname Rufname;Geb.;Nat.;A;VS;Passnummer;Spielrecht ab;Reg. am
 *   Akhabach ;Maryam (w) ;08.09.2012;D;X;;0765-0056;P 08.07.2025 F 08.07.2025;26.11.2025
 *
 * Encoding: Windows-1252
 *
 * "Vorname Rufname" enthält "(w)" oder "(m)" als Geschlechtskennzeichnung des Rufnamens.
 * Diese wird für das gender-Feld extrahiert und aus dem Vornamen entfernt.
 *
 * "Spielrecht ab" = "P DD.MM.YYYY F DD.MM.YYYY"
 *   P = Passdatum, F = Freigabedatum (maßgeblich für Spielberechtigung).
 *   F-Datum wird als eligible_to_play_date (YYYY-MM-DD) gespeichert.
 *   Leer → null → nicht spielberechtigt.
 */
class DfbNetImporter implements ImporterInterface
{
    private const DELIMITER = ';';
    private const ENCODING  = 'Windows-1252';

    // ── Interface-Methoden ────────────────────────────────────────────────────

    public function canHandle(string $filename, string $firstLine): bool
    {
        return str_contains($firstLine, 'Passnummer')
            && str_contains($firstLine, 'Spielrecht');
    }

    public function getSourceName(): string
    {
        return 'dfbnet';
    }

    public function getRawRows(string $rawContent): array
    {
        $content = mb_convert_encoding($rawContent, 'UTF-8', self::ENCODING);
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_filter(
            explode("\n", str_replace("\r\n", "\n", $content)),
            fn (string $l) => trim($l) !== ''
        );

        $rows = [];
        foreach (array_slice(array_values($lines), 1) as $line) {
            $cols = explode(self::DELIMITER, $line);
            if (count($cols) < 2) continue;
            $rows[] = array_map('trim', $cols);
        }

        return $rows;
    }

    public function getColumnHeaders(string $rawContent): array
    {
        $content   = mb_convert_encoding($rawContent, 'UTF-8', self::ENCODING);
        $content   = ltrim($content, "\xEF\xBB\xBF");
        $firstLine = strtok(str_replace("\r\n", "\n", $content), "\n");

        return array_map('trim', explode(self::DELIMITER, $firstLine));
    }

    public function getSuggestedMapping(): array
    {
        return [
            'Name Künstlername' => 'last_name',
            'Vorname Rufname'   => 'first_name',          // (w)/(m) → gender, dann entfernt
            'Geb.'              => 'date_of_birth',
            'Nat.'              => 'skip',
            'A'                 => 'skip',
            'VS'                => 'skip',
            'Passnummer'        => 'pass_number',
            'Spielrecht ab'     => 'eligible_to_play_date', // F-Datum → eligible_to_play_date
            'Reg. am'           => 'skip',
        ];
    }

    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData
    {
        $data   = [];
        $gender = null;

        foreach ($headers as $index => $header) {
            $target = $mapping[$header] ?? 'skip';
            if ($target === 'skip') continue;

            $value = trim($rawRow[$index] ?? '');

            // ── Geschlechtskennzeichnung extrahieren ──────────────────────────
            // DFBnet schreibt "(w)" oder "(m)" in "Vorname Rufname".
            // Wir extrahieren gender defensiv aus allen Spalten.
            if ($gender === null && is_string($value)) {
                if (str_contains($value, '(w)') || str_contains($value, '(W)')) {
                    $gender = 'female';
                } elseif (str_contains($value, '(m)') || str_contains($value, '(M)')) {
                    $gender = 'male';
                }
            }

            // "(w)"/"(m)" aus Namensfeldern entfernen
            if (in_array($target, ['first_name', 'last_name'], true)) {
                $value = trim(preg_replace('/\s*\([wmd]\)\s*/i', '', $value));
            }

            // ── Datum: DD.MM.YYYY → YYYY-MM-DD ───────────────────────────────
            if ($target === 'date_of_birth') {
                if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
                    $value = "{$m[3]}-{$m[2]}-{$m[1]}";
                } else {
                    $value = null;
                }
            }

            // ── Spielberechtigt ab: F-Datum aus DFBnet-Format extrahieren ─────
            // Format: "P 08.07.2025 F 08.07.2025" oder leer
            // P = Passdatum, F = Freigabedatum (Spielrecht gilt ab diesem Datum)
            if ($target === 'eligible_to_play_date') {
                $value = $this->parseEligibleDate($value);
            }

            // Custom Fields: Schlüssel 'cf:slug'
            if (str_starts_with($target, 'cf:')) {
                $slug                         = substr($target, 3);
                $data['custom_fields'][$slug] = $value ?: null;
                continue;
            }

            $data[$target] = ($value !== '' && $value !== null) ? $value : null;
        }

        return new MemberData(
            first_name:            $data['first_name']            ?? '',
            last_name:             $data['last_name']             ?? '',
            date_of_birth:         $data['date_of_birth']         ?? null,
            gender:                $data['gender']                ?? $gender,
            pass_number:           $data['pass_number']           ?? null,
            eligible_to_play_date: $data['eligible_to_play_date'] ?? null,
            status:                $data['status']                ?? 'active',
            custom_fields:         $data['custom_fields']         ?? [],
        );
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Extrahiert das Freigabedatum (F) aus dem DFBnet "Spielrecht ab"-Format.
     *
     * Beispiele:
     *   "P 08.07.2025 F 08.07.2025"  → "2025-07-08"
     *   "F 15.03.2026"               → "2026-03-15"
     *   "15.03.2026"                 → "2026-03-15"  (Fallback: direkte Datumseingabe)
     *   ""                           → null           (kein Spielrecht)
     *
     * @return string|null  YYYY-MM-DD oder null
     */
    private function parseEligibleDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        // Primär: F-Datum extrahieren (Freigabedatum)
        if (preg_match('/F\s+(\d{2})\.(\d{2})\.(\d{4})/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Fallback: direktes DD.MM.YYYY-Format (andere Quellen)
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($value), $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Fallback: bereits YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return trim($value);
        }

        return null;
    }
}
