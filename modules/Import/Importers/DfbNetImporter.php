<?php

declare(strict_types=1);

namespace Modules\Import\Importers;

use Modules\Import\ImporterInterface;
use Modules\Import\MemberData;

/**
 * Importer for DFBnet CSV exports.
 *
 * Typical format:
 *   Name Künstlername;Vorname Rufname;Geb.;Nat.;A;VS;Passnummer;Spielrecht ab;Reg. am
 *   Akhabach ;Maryam (w) ;08.09.2012;D;X;;0765-0056;P 08.07.2025 F 08.07.2025;26.11.2025
 *
 * Encoding: Real DFBNet exports are Windows-1252. The internal decode() method
 * handles both Windows-1252 and UTF-8 input gracefully by checking the encoding
 * before attempting conversion (see decode()).
 *
 * "Vorname Rufname" contains "(w)" or "(m)" as the gender marker of the nickname.
 * This is extracted into the gender field and removed from the first name.
 *
 * "Spielrecht ab" = "P DD.MM.YYYY F DD.MM.YYYY"
 *   P = pass date, F = release date (determines playing eligibility).
 *   The F date is stored as eligible_to_play_date (YYYY-MM-DD).
 *   Empty → null → not eligible.
 */
class DfbNetImporter implements ImporterInterface
{
    private const DELIMITER = ';';
    private const ENCODING  = 'Windows-1252';

    // ── Interface methods ─────────────────────────────────────────────────────

    /**
     * @param  string $filename
     * @param  string $firstLine
     * @return bool
     */
    public function canHandle(string $filename, string $firstLine): bool
    {
        return str_contains($firstLine, 'Passnummer')
            && str_contains($firstLine, 'Spielrecht');
    }

    /** @return string */
    public function getSourceName(): string
    {
        return 'dfbnet';
    }

    /**
     * @param  string $rawContent
     * @return array<int, array<int, string>>
     */
    public function getRawRows(string $rawContent): array
    {
        $content = $this->decode($rawContent);

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

    /**
     * @param  string $rawContent
     * @return array<int, string>
     */
    public function getColumnHeaders(string $rawContent): array
    {
        $content   = $this->decode($rawContent);
        $firstLine = strtok(str_replace("\r\n", "\n", $content), "\n");

        return array_map('trim', explode(self::DELIMITER, $firstLine));
    }

    /**
     * @return array<string, string>
     */
    public function getSuggestedMapping(): array
    {
        return [
            'Name Künstlername' => 'last_name',
            'Vorname Rufname'   => 'first_name',          // (w)/(m) → gender, then stripped
            'Geb.'              => 'date_of_birth',
            'Nat.'              => 'skip',
            'A'                 => 'skip',
            'VS'                => 'skip',
            'Passnummer'        => 'pass_number',
            'Spielrecht ab'     => 'eligible_to_play_date', // F-date → eligible_to_play_date
            'Reg. am'           => 'skip',
        ];
    }

    /**
     * @param  array<int, string>    $rawRow
     * @param  array<int, string>    $headers
     * @param  array<string, string> $mapping
     * @return MemberData
     */
    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData
    {
        $data   = [];
        $gender = null;

        foreach ($headers as $index => $header) {
            $target = $mapping[$header] ?? 'skip';
            if ($target === 'skip') continue;

            $value = trim($rawRow[$index] ?? '');

            // ── Extract gender marker ─────────────────────────────────────────
            // DFBnet writes "(w)" or "(m)" inside "Vorname Rufname".
            // We extract gender defensively from all columns.
            if ($gender === null && is_string($value)) {
                if (str_contains($value, '(w)') || str_contains($value, '(W)')) {
                    $gender = 'female';
                } elseif (str_contains($value, '(m)') || str_contains($value, '(M)')) {
                    $gender = 'male';
                }
            }

            // Strip "(w)"/"(m)" from name fields
            if (in_array($target, ['first_name', 'last_name'], true)) {
                $value = trim(preg_replace('/\s*\([wmd]\)\s*/i', '', $value));
            }

            // ── Date conversion: DD.MM.YYYY → YYYY-MM-DD ──────────────────────
            if ($target === 'date_of_birth') {
                if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
                    $value = "{$m[3]}-{$m[2]}-{$m[1]}";
                } else {
                    $value = null;
                }
            }

            // ── Parse playing eligibility date from DFBnet format ─────────────
            // Format: "P 08.07.2025 F 08.07.2025" or empty
            // P = pass date, F = release date (eligibility starts on this date)
            if ($target === 'eligible_to_play_date') {
                $value = $this->parseEligibleDate($value);
            }

            // Custom fields use the 'cf:slug' key prefix
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

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Decodes raw CSV content to UTF-8, handling both Windows-1252 and UTF-8 input.
     *
     * Real DFBNet exports are Windows-1252 encoded. Test fixtures may be saved
     * as UTF-8 by editors. Without this guard, mb_convert_encoding() would
     * misinterpret UTF-8 multi-byte sequences for umlauts (e.g. ü = 0xC3 0xBC)
     * as two separate Windows-1252 characters ('Ã' + '¼'), corrupting column headers
     * like 'Name Künstlername' → 'Name KÃ¼nstlername' and breaking defaultMapping().
     *
     * mb_check_encoding() reliably distinguishes the two cases:
     *   - Windows-1252 bytes for umlauts (e.g. 0xFC) are NOT valid UTF-8 → false → convert
     *   - UTF-8 content (or ASCII-only) → true → return as-is
     *
     * Also strips the UTF-8 BOM (0xEF 0xBB 0xBF) if present.
     *
     * @param  string $rawContent
     * @return string UTF-8 encoded content without BOM
     */
    private function decode(string $rawContent): string
    {
        if (mb_check_encoding($rawContent, 'UTF-8')) {
            return ltrim($rawContent, "\xEF\xBB\xBF");
        }

        return ltrim(
            mb_convert_encoding($rawContent, 'UTF-8', self::ENCODING),
            "\xEF\xBB\xBF"
        );
    }

    /**
     * Extracts the release date (F) from the DFBnet "Spielrecht ab" format.
     *
     * Examples:
     *   "P 08.07.2025 F 08.07.2025"  → "2025-07-08"
     *   "F 15.03.2026"               → "2026-03-15"
     *   "15.03.2026"                 → "2026-03-15"  (fallback: direct date input)
     *   ""                           → null           (not eligible)
     *
     * @param  string $value
     * @return string|null  YYYY-MM-DD or null
     */
    private function parseEligibleDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        // Primary: extract F-date (release date)
        if (preg_match('/F\s+(\d{2})\.(\d{2})\.(\d{4})/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Fallback: direct DD.MM.YYYY format (other sources)
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($value), $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Fallback: already in YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return trim($value);
        }

        return null;
    }
}