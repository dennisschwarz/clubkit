<?php

declare(strict_types=1);

namespace Modules\Import;

/**
 * Central registry for all available importers.
 *
 * Replaces the hard-coded importer array that previously lived in ImportController.
 * New importers can be registered by external modules via their ServiceProvider
 * without modifying the controller (Open/Closed Principle).
 *
 * Registration in ImportServiceProvider:
 *   $registry->register(new DfbNetImporter());
 *   $registry->register(new NuLigaImporter());
 *
 * Extension by third-party modules (e.g. NuLiga Pro):
 *   app(ImporterRegistry::class)->register(new NuLigaProImporter());
 */
class ImporterRegistry
{
    /** @var ImporterInterface[] */
    private array $importers = [];

    /**
     * Registers an importer.
     * The registration order determines priority in canHandle() lookups.
     *
     * @param  ImporterInterface $importer
     * @return void
     */
    public function register(ImporterInterface $importer): void
    {
        $this->importers[] = $importer;
    }

    /**
     * Returns all registered importers.
     *
     * @return ImporterInterface[]
     */
    public function all(): array
    {
        return $this->importers;
    }

    /**
     * Finds the first importer that can handle the given file.
     * Called on upload to detect the file format.
     *
     * @param  string $filename
     * @param  string $firstLine
     * @return ImporterInterface|null
     */
    public function findByCanHandle(string $filename, string $firstLine): ?ImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->canHandle($filename, $firstLine)) {
                return $importer;
            }
        }
        return null;
    }

    /**
     * Finds an importer by its source name.
     * Used when restoring a saved import session.
     *
     * @param  string $source
     * @return ImporterInterface|null
     */
    public function findBySource(string $source): ?ImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->getSourceName() === $source) {
                return $importer;
            }
        }
        return null;
    }
}
