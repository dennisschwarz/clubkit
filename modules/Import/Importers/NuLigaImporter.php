<?php

declare(strict_types=1);

namespace Modules\Import\Importers;

use Modules\Import\ImporterInterface;
use Modules\Import\MemberData;

/**
 * Placeholder: Importer für NuLiga CSV-Exporte (Handball).
 * Wird implementiert, sobald eine echte NuLiga-Exportdatei vorliegt.
 */
class NuLigaImporter implements ImporterInterface
{
    public function canHandle(string $filename, string $firstLine): bool
    {
        // TODO: NuLiga-Headerkennung implementieren sobald Format bekannt
        return false;
    }

    public function getSourceName(): string
    {
        return 'nuliga';
    }

    public function getRawRows(string $rawContent): array
    {
        // TODO: Implementieren wenn NuLiga-Format bekannt
        return [];
    }

    public function getColumnHeaders(string $rawContent): array
    {
        // TODO: Implementieren wenn NuLiga-Format bekannt
        return [];
    }

    public function getSuggestedMapping(): array
    {
        // TODO: Implementieren wenn NuLiga-Format bekannt
        return [];
    }

    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData
    {
        // TODO: Implementieren wenn NuLiga-Format bekannt
        return new MemberData(
            first_name:    '',
            last_name:     '',
            date_of_birth: null,
            gender:        null,
            pass_number:   null,
        );
    }
}
