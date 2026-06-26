<?php

declare(strict_types=1);

namespace Modules\Import\Services;

use Illuminate\Support\Facades\DB;
use Modules\Import\MemberData;
use Modules\Import\Models\MemberImportLog;
use Modules\Members\Models\Member;

/**
 * Verarbeitet den finalen Import: vergleicht Datensätze mit der DB,
 * schreibt Members + externe IDs in einer einzigen Transaktion.
 *
 * Strategie: Alles-oder-nichts.
 * Schlägt ein Datensatz fehl → kompletter Rollback, kein Halbzustand.
 */
class MemberImportService
{
    // ── Datensatz gegen DB vergleichen ────────────────────────────────────────

    /**
     * @return array{status: string, existing_id: int|null, diff: array}
     */
    public function compare(MemberData $data): array
    {
        $existing = null;

        if ($data->pass_number) {
            $existing = Member::where('pass_number', $data->pass_number)->first();
        }

        if (! $existing && $data->date_of_birth) {
            $existing = Member::where('first_name', $data->first_name)
                              ->where('last_name',  $data->last_name)
                              ->whereDate('date_of_birth', $data->date_of_birth)
                              ->first();
        }

        if (! $existing) {
            return ['status' => 'new', 'existing_id' => null, 'diff' => []];
        }

        $diff   = [];
        $fields = [
            'first_name', 'last_name', 'date_of_birth',
            'gender', 'pass_number', 'eligible_to_play_date', 'status',
        ];

        foreach ($fields as $field) {
            $newVal = $data->$field;
            $oldVal = $existing->$field;

            if ($this->valuesAreEqual($newVal, $oldVal)) continue;

            $diff[$field] = [
                'old' => $oldVal instanceof \DateTimeInterface ? $oldVal->format('Y-m-d') : $oldVal,
                'new' => $newVal,
            ];
        }

        if (empty($diff)) {
            return ['status' => 'unchanged', 'existing_id' => $existing->id, 'diff' => []];
        }

        return ['status' => 'changed', 'existing_id' => $existing->id, 'diff' => $diff];
    }

    // ── Finaler Import (Transaktion) ───────────────────────────────────────────

    /**
     * @param  array<int, array>  $processedRows
     * @param  array<int>         $selectedIndexes
     * @return array{
     *   created:     int,
     *   updated:     int,
     *   skipped:     int,
     *   created_ids: array<int, int>  ← rowIndex => memberId (für Team-Zuweisung)
     * }
     *
     * @throws \Throwable
     */
    public function execute(
        array $processedRows,
        array $selectedIndexes,
        string $source,
        string $filename,
        int $importedBy,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'created_ids' => []];

        DB::transaction(function () use (
            $processedRows, $selectedIndexes, $source, $filename, $importedBy, &$stats
        ) {
            foreach ($processedRows as $index => $row) {
                if (! in_array($index, $selectedIndexes, strict: true)) {
                    if ($row['status'] !== 'unchanged') $stats['skipped']++;
                    continue;
                }

                $mapped = $row['mapped'];
                $status = $row['status'];

                if ($status === 'unchanged') continue;

                if ($status === 'new') {
                    $member = Member::create([
                        'first_name'            => $mapped['first_name']            ?? '',
                        'last_name'             => $mapped['last_name']             ?? '',
                        'date_of_birth'         => $mapped['date_of_birth']         ?? null,
                        'gender'                => $mapped['gender']                ?? null,
                        'pass_number'           => $mapped['pass_number']           ?? null,
                        'eligible_to_play_date' => $mapped['eligible_to_play_date'] ?? null,
                        'status'                => $mapped['status']                ?? 'active',
                        'created_by'            => $importedBy,
                    ]);

                    $this->upsertExternalId($member->id, $source, $mapped['pass_number'] ?? null);
                    $this->writeCustomFields($member->id, $row['custom_fields'] ?? []);

                    // rowIndex → memberId für spätere per-Zeile Team-Zuweisung
                    $stats['created_ids'][$index] = $member->id;
                    $stats['created']++;

                } elseif ($status === 'changed') {
                    $member = Member::findOrFail($row['existing_id']);
                    $member->update([
                        'first_name'            => $mapped['first_name']            ?? $member->first_name,
                        'last_name'             => $mapped['last_name']             ?? $member->last_name,
                        'date_of_birth'         => $mapped['date_of_birth']         ?? $member->date_of_birth,
                        'gender'                => $mapped['gender']                ?? $member->gender,
                        'pass_number'           => $mapped['pass_number']           ?? $member->pass_number,
                        'eligible_to_play_date' => $mapped['eligible_to_play_date'] ?? $member->eligible_to_play_date,
                        'status'                => $mapped['status']                ?? $member->status,
                    ]);

                    $this->upsertExternalId($member->id, $source, $mapped['pass_number'] ?? null);
                    $this->writeCustomFields($member->id, $row['custom_fields'] ?? []);

                    $stats['updated']++;
                }
            }

            MemberImportLog::create([
                'source'        => $source,
                'filename'      => $filename,
                'created_count' => $stats['created'],
                'updated_count' => $stats['updated'],
                'skipped_count' => $stats['skipped'],
                'created_by'    => $importedBy,
            ]);
        });

        return $stats;
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function valuesAreEqual(mixed $newVal, mixed $oldVal): bool
    {
        if ($oldVal instanceof \DateTimeInterface) $oldVal = $oldVal->format('Y-m-d');
        if ($newVal instanceof \DateTimeInterface) $newVal = $newVal->format('Y-m-d');

        $newStr = ($newVal === null) ? '' : trim((string) $newVal);
        $oldStr = ($oldVal === null) ? '' : trim((string) $oldVal);

        return $newStr === $oldStr;
    }

    private function upsertExternalId(int $memberId, string $source, ?string $externalId): void
    {
        if (! $externalId) return;

        DB::table('member_external_ids')->upsert(
            [
                'member_id'   => $memberId,
                'source'      => $source,
                'external_id' => $externalId,
                'imported_on' => now()->toDateString(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            uniqueBy: ['member_id', 'source'],
            update:   ['external_id', 'imported_on', 'updated_at'],
        );
    }

    /**
     * Schreibt Custom-Field-Werte für ein Mitglied.
     *
     * WICHTIG: Die Tabelle custom_field_values hat die Spalte 'field_id' (nicht 'definition_id').
     * Der FK heißt field_id → custom_field_definitions.id.
     */
    private function writeCustomFields(int $memberId, array $customFields): void
    {
        if (empty($customFields)) return;
        if (! class_exists(\Modules\CustomFields\Models\CustomFieldDefinition::class)) return;

        foreach ($customFields as $slug => $value) {
            $definition = \Modules\CustomFields\Models\CustomFieldDefinition::where('slug', $slug)
                            ->where('object_type', 'member')
                            ->first();

            if (! $definition) continue;

            // Spaltenname ist 'field_id', nicht 'definition_id'!
            \Modules\CustomFields\Models\CustomFieldValue::updateOrCreate(
                ['field_id' => $definition->id, 'entity_id' => $memberId],
                ['value'    => $value],
            );
        }
    }
}
