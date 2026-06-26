<?php

declare(strict_types=1);

namespace Modules\Import\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Import\Importers\DfbNetImporter;
use Modules\Import\Importers\NuLigaImporter;
use Modules\Import\ImporterInterface;
use Modules\Import\Models\ImportSession;
use Modules\Import\Services\MemberImportService;

class ImportController extends Controller
{
    private array $importers;

    public function __construct(private readonly MemberImportService $importService)
    {
        $this->importers = [
            new DfbNetImporter(),
            new NuLigaImporter(),
        ];
    }

    public function index(): View
    {
        return view('import::step1-upload');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'csv_file.required' => 'Bitte eine CSV-Datei auswählen.',
            'csv_file.mimes'    => 'Nur CSV-Dateien werden unterstützt.',
            'csv_file.max'      => 'Maximale Dateigröße: 10 MB.',
        ]);

        $file       = $request->file('csv_file');
        $filename   = $file->getClientOriginalName();
        $rawContent = file_get_contents($file->getPathname());

        $firstLine = strtok(
            str_replace(["\r\n", "\r"], "\n", mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252')),
            "\n"
        );

        $importer = $this->findImporterByCanHandle($filename, $firstLine);
        if (! $importer) {
            return back()->withErrors(['csv_file' => 'Dateiformat nicht erkannt.']);
        }

        $headers = $importer->getColumnHeaders($rawContent);
        $rawRows = $importer->getRawRows($rawContent);

        if (empty($rawRows)) {
            return back()->withErrors(['csv_file' => 'Die Datei enthält keine Datensätze.']);
        }

        $samples = [];
        foreach ($headers as $i => $header) {
            $vals             = array_column($rawRows, $i);
            $unique           = array_values(array_unique(array_filter($vals, fn ($v) => $v !== '')));
            $samples[$header] = array_slice($unique, 0, 3);
        }

        $session = ImportSession::create([
            'created_by'     => $request->user()->id,
            'source'         => $importer->getSourceName(),
            'filename'       => $filename,
            'column_headers' => $headers,
            'raw_rows'       => $rawRows,
            'samples'        => $samples,
            'expires_at'     => now()->addHours(2),
        ]);

        return redirect()->route('import.mapping', $session->id);
    }

    public function mapping(string $sessionId): View|RedirectResponse
    {
        $session = $this->findSession($sessionId);
        if (! $session) return $this->sessionExpired();

        $importer  = $this->findImporterBySource($session->source);
        $suggested = $importer ? $importer->getSuggestedMapping() : [];

        $memberFields        = $this->getMemberFields();
        $customFields        = [];
        $objectTypes         = [];
        $fieldTypes          = [];
        $customFieldsEnabled = class_exists(\Modules\CustomFields\Models\CustomFieldDefinition::class);

        if ($customFieldsEnabled) {
            $customFields = \Modules\CustomFields\Models\CustomFieldDefinition::where('object_type', 'member')
                              ->orderBy('label')
                              ->get(['id', 'slug', 'label', 'field_type'])
                              ->toArray();

            $objectTypes = \Modules\CustomFields\Services\CustomFieldRegistry::availableObjectTypes();
            $fieldTypes  = \Modules\CustomFields\Services\CustomFieldRegistry::fieldTypes();
        }

        return view('import::step2-mapping', compact(
            'session', 'suggested', 'memberFields',
            'customFields', 'customFieldsEnabled',
            'objectTypes', 'fieldTypes',
        ));
    }

    public function saveMapping(Request $request, string $sessionId): RedirectResponse
    {
        $session = $this->findSession($sessionId);
        if (! $session) return $this->sessionExpired();

        $importer = $this->findImporterBySource($session->source);
        if (! $importer) return $this->sessionExpired();

        $mapping = $request->input('mapping', []);
        $headers = $session->column_headers;
        $rawRows = $session->raw_rows;

        $processedRows = [];
        foreach ($rawRows as $index => $rawRow) {
            $memberData = $importer->applyMapping($rawRow, $headers, $mapping);
            $comparison = $this->importService->compare($memberData);

            $processedRows[$index] = [
                'raw'           => $rawRow,
                'mapped'        => [
                    'first_name'            => $memberData->first_name,
                    'last_name'             => $memberData->last_name,
                    'date_of_birth'         => $memberData->date_of_birth,
                    'gender'                => $memberData->gender,
                    'pass_number'           => $memberData->pass_number,
                    'eligible_to_play_date' => $memberData->eligible_to_play_date,
                    'status'                => $memberData->status,
                ],
                'custom_fields' => $memberData->custom_fields,
                'status'        => $comparison['status'],
                'existing_id'   => $comparison['existing_id'],
                'diff'          => $comparison['diff'],
            ];
        }

        $session->update([
            'mapping'        => $mapping,
            'processed_rows' => $processedRows,
        ]);

        return redirect()->route('import.preview', $session->id);
    }

    public function preview(string $sessionId): View|RedirectResponse
    {
        $session = $this->findSession($sessionId);
        if (! $session) return $this->sessionExpired();

        if (empty($session->processed_rows)) {
            return redirect()->route('import.mapping', $session->id);
        }

        $rows = $session->processed_rows;

        $counts = [
            'total'     => count($rows),
            'new'       => count(array_filter($rows, fn ($r) => $r['status'] === 'new')),
            'changed'   => count(array_filter($rows, fn ($r) => $r['status'] === 'changed')),
            'unchanged' => count(array_filter($rows, fn ($r) => $r['status'] === 'unchanged')),
        ];

        $teams = [];
        if (class_exists(\Modules\Teams\Models\Team::class)) {
            $teams = \Modules\Teams\Models\Team::where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'name', 'eligible_only'])
                        ->all();
        }

        return view('import::step3-preview', compact('session', 'rows', 'counts', 'teams'));
    }

    public function execute(Request $request, string $sessionId): RedirectResponse
    {
        $session = $this->findSession($sessionId);
        if (! $session) return $this->sessionExpired();

        $selectedIndexes = array_map('intval', $request->input('selected', []));

        if (empty($selectedIndexes)) {
            return back()->withErrors(['selected' => 'Keine Datensätze ausgewählt.']);
        }

        try {
            $stats = $this->importService->execute(
                processedRows:   $session->processed_rows,
                selectedIndexes: $selectedIndexes,
                source:          $session->source,
                filename:        $session->filename,
                importedBy:      $request->user()->id,
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'import' => 'Import fehlgeschlagen – alle Änderungen wurden rückgängig gemacht. Fehler: ' . $e->getMessage(),
            ]);
        }

        // Per-Zeile Team-Zuweisung (außerhalb der Haupttransaktion)
        // assign_team_id[rowIndex] = teamId (leer = kein Team für diesen Datensatz)
        $teamAssignments = $request->input('assign_team_id', []);
        if (! empty($teamAssignments) && ! empty($stats['created_ids'])) {
            $this->assignPerRow($teamAssignments, $stats['created_ids']);
        }

        $session->delete();

        return redirect()->route('members.index')->with('success',
            sprintf(
                'Import abgeschlossen: %d angelegt, %d aktualisiert, %d übersprungen.',
                $stats['created'],
                $stats['updated'],
                $stats['skipped'],
            )
        );
    }

    public function cancel(string $sessionId): RedirectResponse
    {
        $session = ImportSession::find($sessionId);
        $session?->delete();

        return redirect()->route('members.index')->with('info', 'Import abgebrochen.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Per-Zeile Team-Zuweisung nach dem Import.
     *
     * @param  array<int|string, string>  $teamAssignments  rowIndex => teamId (als String aus Request)
     * @param  array<int, int>            $createdIds        rowIndex => memberId
     */
    private function assignPerRow(array $teamAssignments, array $createdIds): void
    {
        if (! class_exists(\Modules\Teams\Models\Team::class)) return;

        // Teams vorab laden um N+1 zu vermeiden
        $teamIds = array_unique(array_filter(array_map('intval', $teamAssignments)));
        if (empty($teamIds)) return;

        $teamsById = \Modules\Teams\Models\Team::whereIn('id', $teamIds)
                        ->get()
                        ->keyBy('id');

        // Gruppierung: teamId → [memberIds]
        $grouped = [];
        foreach ($createdIds as $rowIndex => $memberId) {
            $teamId = (int) ($teamAssignments[$rowIndex] ?? 0);
            if (! $teamId) continue;
            $grouped[$teamId][] = $memberId;
        }

        foreach ($grouped as $teamId => $memberIds) {
            $team = $teamsById->get($teamId);
            if (! $team) continue;

            // Bereits zugewiesene herausfiltern
            $existingIds = $team->members()->pluck('members.id')->toArray();
            $toAssign    = array_diff($memberIds, $existingIds);
            if (empty($toAssign)) continue;

            // Wenn eligible_only: nur spielberechtigte zuweisen
            if ($team->eligible_only) {
                $toAssign = \Modules\Members\Models\Member::whereIn('id', $toAssign)
                                ->whereNotNull('eligible_to_play_date')
                                ->whereDate('eligible_to_play_date', '<=', now())
                                ->pluck('id')
                                ->toArray();
            }

            if (empty($toAssign)) continue;

            $team->members()->attach($toAssign);
        }
    }

    private function findSession(string $id): ?ImportSession
    {
        $session = ImportSession::find($id);
        if (! $session || $session->isExpired()) return null;
        return $session;
    }

    private function findImporterByCanHandle(string $filename, string $firstLine): ?ImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->canHandle($filename, $firstLine)) return $importer;
        }
        return null;
    }

    private function findImporterBySource(string $source): ?ImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->getSourceName() === $source) return $importer;
        }
        return null;
    }

    private function sessionExpired(): RedirectResponse
    {
        return redirect()->route('import.index')
            ->withErrors(['session' => 'Die Import-Sitzung ist abgelaufen oder ungültig. Bitte erneut hochladen.']);
    }

    private function getMemberFields(): array
    {
        return [
            'first_name'            => 'Vorname',
            'last_name'             => 'Nachname',
            'date_of_birth'         => 'Geburtsdatum',
            'gender'                => 'Geschlecht',
            'pass_number'           => 'Passnummer',
            'eligible_to_play_date' => 'Spielberechtigt ab (Datum)',
            'status'                => 'Status (active/inactive)',
        ];
    }
}
