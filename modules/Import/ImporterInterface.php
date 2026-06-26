<?php

declare(strict_types=1);

namespace Modules\Import;

/**
 * Vertrag für alle Import-Quellen.
 * Neue Quelle = neue Klasse, die dieses Interface implementiert.
 * Kein anderer Code muss geändert werden.
 */
interface ImporterInterface
{
    /**
     * Prüft ob diese Datei von diesem Importer verarbeitet werden kann.
     * Entscheidung basiert auf Dateiname und erster Zeile (Header).
     */
    public function canHandle(string $filename, string $firstLine): bool;

    /**
     * Interner Quellname – wird im Import-Log gespeichert.
     * Beispiele: 'dfbnet', 'nuliga'
     */
    public function getSourceName(): string;

    /**
     * Dekodiert den Roh-Dateiinhalt und gibt alle Datenzeilen als 2D-Array zurück.
     * Encoding-Konvertierung findet hier statt (z.B. Windows-1252 → UTF-8).
     * Die Header-Zeile wird übersprungen.
     *
     * @return array<int, array<int, string>>
     */
    public function getRawRows(string $rawContent): array;

    /**
     * Liest die Spaltenköpfe aus dem Dateiinhalt.
     *
     * @return array<int, string>
     */
    public function getColumnHeaders(string $rawContent): array;

    /**
     * Vorgeschlagene Spalten-Zuordnung (wird in Stufe 2 als Default gesetzt).
     * Schlüssel = Spaltenname aus CSV, Wert = members-Tabellenfeld oder 'skip'.
     *
     * @return array<string, string>
     */
    public function getSuggestedMapping(): array;

    /**
     * Wendet das vom User bestätigte Mapping auf eine Roh-Zeile an
     * und gibt ein quellneutrales MemberData-DTO zurück.
     *
     * @param array<int, string>    $rawRow  Eine Zeile aus getRawRows()
     * @param array<int, string>    $headers Spaltenköpfe aus getColumnHeaders()
     * @param array<string, string> $mapping Bestätigtes Mapping aus Stufe 2
     */
    public function applyMapping(array $rawRow, array $headers, array $mapping): MemberData;
}
