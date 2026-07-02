<?php

declare(strict_types=1);

namespace Modules\Import\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Import\Http\Requests\SaveMappingRequest;
use Modules\Import\Http\Requests\UploadCsvRequest;
use Modules\Import\ImporterRegistry;
use Modules\Import\Models\ImportSession;
use Modules\Import\Services\MemberImportService;

/**
 * Drives the three-step CSV import wizard.
 *
 * Step 1 (index/upload): file upload and format detection via ImporterRegistry.
 * Step 2 (mapping/saveMapping): column-to-field assignment confirmed by the user.
 * Step 3 (preview/execute): row comparison and final write inside one transaction.
 *
 * Validation is delegated to UploadCsvRequest and SaveMappingRequest.
 * The ImporterRegistry is injected – no hard-coded importer instances in the controller.
 */
class ImportController extends Controller
{
    /**
     * @param  MemberImportService $importService
     * @param  ImporterRegistry    $importerRegistry
     */
    public function __construct(
        private readonly MemberImportService $importService,
        private readonly ImporterRegistry    $importerRegistry,
    ) {}

    /**
     * Renders the file upload form (step 1).
     *
     * @return View
     */
    public function index(): View
    {
        return view('import::step1-upload');
    }

    /**
     * Processes the uploaded CSV file and redirects to the mapping step.
     *
     * Validation is handled by UploadCsvRequest – no manual $request->validate() here.
     *
     * @param  UploadCsvRequest $request
     * @return RedirectResponse
     */
    public function upload(UploadCsvRequest $request): RedirectResponse
    {
        $file       = $request->file('csv_file');
        $filename   = $file->getClientOriginalName();
        $rawContent = file_get_contents($file->getPathname());

        $firstLine = strtok(
            str_replace(["\r\n", "\r"], "\n", mb_convert_encoding($rawContent, 'UTF-8', 'Windows-1252')),
            "\n"
        );

        // Use the registry to detect the file format – avoids hardcoded array checks
        $importer = $this->importerRegistry->findByCanHandle($filename, $firstLine);
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

    /**
     * Renders the column mapping form (step 2).
     *
     * @param  Request $request
     * @param  string  $sessionId
     * @return View|RedirectResponse
     */
    public function mapping(Request $request, string $sessionId): View|RedirectResponse
    {
        $session = $this->findSession($sessionId, $request->user()->id);
        if (! $session) return $this->sessionExpired();

        // Resolve the importer from the registry using the stored source name
        $importer  = $this->importerRegistry->findBySource($session->source);
        $suggested = $importer ? $importer->getSuggestedMapping() : [];

        $memberFields = $this->getMemberFields();

        $customFields = [];
        $objectTypes  = [];
        $fieldTypes   = [];

        // Schema::hasTable() checks the actual DB structure (not class_exists()).
        // class_exists() can return a false-positive after a module is uninstalled
        // as long as the autoloader cache has not been cleared.
        $customFieldsEnabled = Schema::hasTable('custom_field_definitions');

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

    /**
     * Saves the column mapping and processes all rows (step 2 → 3).
     *
     * Validation is handled by SaveMappingRequest.
     *
     * @param  SaveMappingRequest $request
     * @param  string             $sessionId
     * @return RedirectResponse
     */
    public function saveMapping(SaveMappingRequest $request, string $sessionId): RedirectResponse
    {
        $session = $this->findSession($sessionId, $request->user()->id);
        if (! $session) return $this->sessionExpired();

        $importer = $this->importerRegistry->findBySource($session->source);
        if (! $importer) return $this->sessionExpired();

        $mapping = $request->validated()['mapping'] ?? [];
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

    /**
     * Renders the preview page with row comparison results (step 3).
     *
     * @param  Request $request
     * @param  string  $sessionId
     * @return View|RedirectResponse
     */
    public function preview(Request $request, string $sessionId): View|RedirectResponse
    {
        $session = $this->findSession($sessionId, $request->user()->id);
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
        // Schema::hasTable() checks the actual DB structure (not class_exists())
        if (Schema::hasTable('teams')) {
            $teams = \Modules\Teams\Models\Team::where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'name', 'eligible_only'])
                        ->all();
        }

        return view('import::step3-preview', compact('session', 'rows', 'counts', 'teams'));
    }

    /**
     * Executes the final import for the selected rows.
     *
     * Member import and team assignment run inside a single atomic transaction.
     * If team assignment fails, the created members are also rolled back.
     *
     * @param  Request $request
     * @param  string  $sessionId
     * @return RedirectResponse
     */
    public function execute(Request $request, string $sessionId): RedirectResponse
    {
        $session = $this->findSession($sessionId, $request->user()->id);
        if (! $session) return $this->sessionExpired();

        $selectedIndexes = array_map('intval', $request->input('selected', []));

        if (empty($selectedIndexes)) {
            return back()->withErrors(['selected' => 'Keine Datensätze ausgewählt.']);
        }

        // Read team assignments from the request before entering the transaction
        $teamAssignments = $request->input('assign_team_id', []);
        $stats           = null;

        try {
            DB::transaction(function () use ($session, $selectedIndexes, $request, $teamAssignments, &$stats): void {
                $stats = $this->importService->execute(
                    processedRows:   $session->processed_rows,
                    selectedIndexes: $selectedIndexes,
                    source:          $session->source,
                    filename:        $session->filename,
                    importedBy:      $request->user()->id,
                );

                if (! empty($teamAssignments) && ! empty($stats['selected_ids'])) {
                    $this->assignPerRow($teamAssignments, $stats['selected_ids']);
                }
            });
        } catch (\Throwable $e) {
            // Never expose SQL, file paths, or stack traces to the user
            Log::error('Import fehlgeschlagen', [
                'session_id' => $sessionId,
                'user_id'    => $request->user()->id,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'import' => 'Import fehlgeschlagen – alle Änderungen wurden rückgängig gemacht. Bitte den Administrator kontaktieren.',
            ]);
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

    /**
     * Cancels an import session and deletes it.
     *
     * @param  string $sessionId
     * @return RedirectResponse
     */
    public function cancel(string $sessionId): RedirectResponse
    {
        $session = ImportSession::find($sessionId);
        $session?->delete();

        return redirect()->route('members.index')->with('info', 'Import abgebrochen.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Assigns newly created members to teams on a per-row basis.
     * Called inside DB::transaction() in execute() – rolls back with the rest on failure.
     *
     * joined_at is set explicitly to match TeamController::addMember() behavior.
     * Pivot array format: [member_id => pivot_data] for a single batched INSERT per team.
     *
     * @param  array<int|string, string>  $teamAssignments  rowIndex → teamId
     * @param  array<int, int>            $memberIds         rowIndex → memberId
     * @return void
     */
    private function assignPerRow(array $teamAssignments, array $memberIds): void
    {
        // Schema::hasTable() checks the actual DB structure (not class_exists())
        if (! Schema::hasTable('teams')) return;

        $teamIds = array_unique(array_filter(array_map('intval', $teamAssignments)));
        if (empty($teamIds)) return;

        $teamsById = \Modules\Teams\Models\Team::whereIn('id', $teamIds)
                        ->get()
                        ->keyBy('id');

        $grouped = [];
        foreach ($memberIds as $rowIndex => $memberId) {
            $teamId = (int) ($teamAssignments[$rowIndex] ?? 0);
            if (! $teamId) continue;
            $grouped[$teamId][] = $memberId;
        }

        foreach ($grouped as $teamId => $rowMemberIds) {
            $team = $teamsById->get($teamId);
            if (! $team) continue;

            $existingIds = $team->members()->pluck('members.id')->toArray();
            $toAssign    = array_diff($rowMemberIds, $existingIds);
            if (empty($toAssign)) continue;

            if ($team->eligible_only) {
                $toAssign = \Modules\Members\Models\Member::whereIn('id', $toAssign)
                                ->whereNotNull('eligible_to_play_date')
                                ->whereDate('eligible_to_play_date', '<=', now())
                                ->pluck('id')
                                ->toArray();
            }

            if (empty($toAssign)) continue;

            // Set joined_at explicitly – consistent with TeamController::addMember().
            // Pivot array: [member_id => pivot_data] for a single batched INSERT per team.
            $pivotData = array_fill_keys($toAssign, ['joined_at' => now()]);
            $team->members()->attach($pivotData);
        }
    }

    /**
     * Loads an import session and verifies ownership.
     *
     * Only the session owner can access it – prevents session hijacking via URL manipulation.
     * The authenticated user's ID is passed explicitly from the calling controller method
     * ($request->user()->id) – never resolved via auth()->id() here.
     *
     * @param  string $id
     * @param  int    $userId
     * @return ImportSession|null
     */
    private function findSession(string $id, int $userId): ?ImportSession
    {
        $session = ImportSession::find($id);

        if (! $session || $session->isExpired()) return null;

        if ($session->created_by === null || (int) $session->created_by !== $userId) {
            return null;
        }

        return $session;
    }

    /**
     * Returns a redirect to the upload page with a session-expired error message.
     *
     * @return RedirectResponse
     */
    private function sessionExpired(): RedirectResponse
    {
        return redirect()->route('import.index')
            ->withErrors(['session' => 'Die Import-Sitzung ist abgelaufen oder ungültig. Bitte erneut hochladen.']);
    }

    /**
     * Returns the list of standard member fields for the mapping UI.
     *
     * @return array<string, string>
     */
    private function getMemberFields(): array
    {
        return [
            'first_name'            => 'Vorname',
            'last_name'             => 'Nachname',
            'date_of_birth'         => 'Geburtsdatum',
            'gender'                => 'Geschlecht',
            'pass_number'           => 'Passnummer',
            'eligible_to_play_date' => 'Spielberechtigung ab',
            'status'                => 'Status',
        ];
    }
}
