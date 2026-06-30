<?php

declare(strict_types=1);

namespace Modules\Import\Importers;

use Modules\Import\ImporterInterface;
use Modules\Import\MemberData;

/**
 * Placeholder importer for NuLiga CSV exports (handball).
 * To be implemented once a real NuLiga export file is available.
 */
class NuLigaImporter implements ImporterInterface
{
    /**
     * @param  string $filename
     * @param  string $firstLine
     * @return bool
     */
    public function canHandle(string $filename, string $firstLine): bool
    {
        // TODO: implement NuLiga header detection once the format is known
        return false;
    }

    /** @return string */
    public function getSourceName(): string
    {
        return 'nuliga';
    }

    /**
     * @param  string $rawContent
     * @return array<int, array<int, string>>
     */
    public function getRawRows(string $rawContent): array
    {
        // TODO: implement when NuLiga format is known
        return [];
    }

    /**
     * @param  string $rawContent
     * @return array<int, string>
     */
    public function getColumnHeaders(string $rawContent): array
    {
        // TODO: implement when NuLiga format is known
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getSuggestedMapping(): array
    {
        // TODO: implement when NuLiga format is known
        return [];
    }

    /**
     * @param  array<int, string>    $rawRow
     * @param  array<int, string>    $headers
     * @param  array<string, string> $mapping
     * @return MemberData
     */
    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData
    {
        // TODO: implement when NuLiga format is known
        return new MemberData(
            first_name:    '',
            last_name:     '',
            date_of_birth: null,
            gender:        null,
            pass_number:   null,
        );
    }
}
