<?php

declare(strict_types=1);

namespace Modules\Import;

/**
 * Contract for all CSV import sources.
 * Adding a new source means creating a new class that implements this interface.
 * No other code needs to be changed.
 */
interface ImporterInterface
{
    /**
     * Returns true when this importer can process the given file.
     * The decision is based on the filename and the first line (header row).
     *
     * @param  string $filename
     * @param  string $firstLine
     * @return bool
     */
    public function canHandle(string $filename, string $firstLine): bool;

    /**
     * Returns the internal source name stored in the import log.
     * Examples: 'dfbnet', 'nuliga'
     *
     * @return string
     */
    public function getSourceName(): string;

    /**
     * Decodes the raw file content and returns all data rows as a 2D array.
     * Encoding conversion happens here (e.g. Windows-1252 → UTF-8).
     * The header row is skipped.
     *
     * @param  string $rawContent
     * @return array<int, array<int, string>>
     */
    public function getRawRows(string $rawContent): array;

    /**
     * Reads the column headers from the file content.
     *
     * @param  string $rawContent
     * @return array<int, string>
     */
    public function getColumnHeaders(string $rawContent): array;

    /**
     * Returns the suggested column mapping (used as default in step 2).
     * Keys = CSV column name, values = members table field or 'skip'.
     *
     * @return array<string, string>
     */
    public function getSuggestedMapping(): array;

    /**
     * Applies the user-confirmed mapping to a raw row and returns
     * a source-neutral MemberData DTO.
     *
     * @param  array<int, string>    $rawRow  One row from getRawRows()
     * @param  array<int, string>    $headers Column headers from getColumnHeaders()
     * @param  array<string, string> $mapping Confirmed mapping from step 2
     * @return MemberData
     */
    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData;
}
