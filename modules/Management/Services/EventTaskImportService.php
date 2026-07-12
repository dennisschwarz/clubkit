<?php

declare(strict_types=1);

namespace Modules\Management\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;

/**
 * Parses a CSV file and creates EventTask records for a given event.
 *
 * The import runs in two distinct phases so the controller can present
 * a browser-side preview before committing anything to the database:
 *
 *   Phase 1 — parseCsv()
 *     Pure parsing and row-level validation. No DB access.
 *     Returns a list of ParsedTaskRow DTOs with status 'ok' | 'invalid'.
 *
 *   Phase 2 — execute()
 *     Transactional writes. Receives the rows the user confirmed
 *     (posted as JSON from the browser after editing / drag-drop).
 *     Invalid rows are skipped. Missing categories are created automatically.
 *
 * CSV format (comma or semicolon, UTF-8 + optional BOM):
 *   name, category, priority, deadline, notes,
 *   slot_start, slot_end, interval_minutes, capacity
 *
 * A row with slot_start + slot_end + interval_minutes → Einsatzplan task.
 * A row without those fields → regular event task.
 */
class EventTaskImportService
{
    /** Valid EventTask priority values (mirrors EventTask::PRIORITIES). */
    private const VALID_PRIORITIES = ['normal', 'important', 'critical'];

    /** Valid slot interval values in minutes. */
    private const VALID_INTERVALS = [15, 30, 45, 60, 90, 120];

    /**
     * Colour tokens assigned to auto-created categories.
     * Applied in order; first token not yet in use for this event wins.
     * Falls back to 'gray' when all tokens are taken.
     */
    private const CATEGORY_COLORS = [
        'blue', 'teal', 'green', 'orange', 'red',
        'navy', 'amber', 'purple', 'pink', 'slate', 'gray',
    ];

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Parses raw CSV content and returns one ParsedTaskRow per data row.
     *
     * Handles:
     *  - UTF-8 BOM stripping (\xEF\xBB\xBF prepended by Excel on Windows)
     *  - Automatic delimiter detection: comma vs. semicolon (German Excel default)
     *  - Case-insensitive header matching with German column aliases
     *  - Row-level validation; sets status 'ok' | 'invalid' + errors list
     *  - Blank-line skipping
     *
     * @param  string $contents Raw file content from the uploaded CSV.
     * @return list<ParsedTaskRow>
     */
    public function parseCsv(string $contents): array
    {
        $contents = $this->stripBom($contents);
        $lines    = preg_split('/\r\n|\r|\n/', trim($contents));

        if (! $lines || count($lines) < 2) {
            return [];
        }

        $headerLine = array_shift($lines);
        $delimiter  = $this->detectDelimiter($headerLine);
        $rawHeaders = str_getcsv($headerLine, $delimiter);
        $headers    = array_map(static fn ($h) => strtolower(trim((string) $h)), $rawHeaders);

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = $this->buildRow($headers, str_getcsv($line, $delimiter));
        }

        return $rows;
    }

    /**
     * Persists selected rows as EventTask records inside a single DB transaction.
     *
     * Rows with status 'invalid' are skipped (counted in the returned summary).
     * Category names not yet present on this event are created automatically.
     *
     * @param  object              $event     Event model instance.
     * @param  int                 $createdBy Authenticated user id.
     * @param  list<ParsedTaskRow> $rows      Output from parseCsv(); caller may pre-filter.
     * @return array{imported: int, skipped: int}
     */
    public function execute(object $event, int $createdBy, array $rows): array
    {
        $imported      = 0;
        $skipped       = 0;
        $categoryCache = []; // normalised-name → category id

        DB::transaction(function () use ($event, $createdBy, $rows, &$imported, &$skipped, &$categoryCache) {
            foreach ($rows as $row) {
                if ($row->status === 'invalid') {
                    $skipped++;
                    continue;
                }

                $categoryId = null;

                if ($row->category !== null && $row->category !== '') {
                    $categoryId = $this->findOrCreateCategory(
                        $event->id,
                        $row->category,
                        $createdBy,
                        $categoryCache
                    );
                }

                EventTask::create([
                    'event_id'              => $event->id,
                    'category_id'           => $categoryId,
                    'name'                  => $row->name,
                    'priority'              => $row->priority,
                    'deadline_at'           => $row->deadline,
                    'notes'                 => $row->notes,
                    'created_by'            => $createdBy,
                    'slot_start_time'       => $row->slotStartTime,
                    'slot_end_time'         => $row->slotEndTime,
                    'slot_interval_minutes' => $row->slotIntervalMinutes,
                    'slot_capacity'         => $row->slotCapacity ?? 1,
                ]);

                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Maps one raw CSV row to a ParsedTaskRow DTO and validates it.
     *
     * Column matching is case-insensitive and accepts German aliases:
     *   kategorie              → category
     *   priorität/prioritaet   → priority
     *   notizen/anmerkungen    → notes
     *   start / slot_start     → slotStartTime
     *   end/ende / slot_end    → slotEndTime
     *   interval_minuten       → slotIntervalMinutes
     *   kapazität/kapazitaet   → slotCapacity
     *
     * @param  list<string> $headers  Lowercased, trimmed header names.
     * @param  list<string> $cells    Raw cell values for this row.
     * @return ParsedTaskRow
     */
    private function buildRow(array $headers, array $cells): ParsedTaskRow
    {
        /**
         * Returns the first non-empty cell matching any alias, or null.
         *
         * @param  list<string> $aliases
         */
        $get = static function (array $aliases) use ($headers, $cells): ?string {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $headers, true);
                if ($idx !== false && isset($cells[$idx])) {
                    $value = trim($cells[$idx]);
                    return $value !== '' ? $value : null;
                }
            }
            return null;
        };

        $name        = $get(['name']);
        $category    = $get(['category', 'kategorie']);
        $priorityRaw = $get(['priority', 'priorität', 'prioritaet']) ?? 'normal';
        $deadlineRaw = $get(['deadline']);
        $notes       = $get(['notes', 'notizen', 'anmerkungen']);
        $slotStart   = $get(['slot_start', 'start']);
        $slotEnd     = $get(['slot_end', 'end', 'ende']);
        $intervalRaw = $get(['interval_minutes', 'interval', 'interval_minuten']);
        $capacityRaw = $get(['capacity', 'kapazität', 'kapazitaet']);

        $priority = strtolower($priorityRaw);
        $interval = $intervalRaw !== null ? (int) $intervalRaw : null;
        $capacity = $capacityRaw !== null ? max(1, (int) $capacityRaw) : null;

        // Parse deadline as Y-m-d; silently discard unparseable values.
        $deadline = null;
        if ($deadlineRaw !== null) {
            try {
                $deadline = Carbon::parse($deadlineRaw)->toDateString();
            } catch (\Throwable) {
                $deadline = null;
            }
        }

        $row = new ParsedTaskRow(
            name:                $name ?? '',
            category:            $category,
            priority:            $priority,
            deadline:            $deadline,
            notes:               $notes,
            slotStartTime:       $slotStart,
            slotEndTime:         $slotEnd,
            slotIntervalMinutes: $interval,
            slotCapacity:        $capacity,
        );

        return $this->validateRow($row);
    }

    /**
     * Validates all fields on a ParsedTaskRow and sets status / errors in-place.
     *
     * Rules:
     *  - name must be non-empty and at most 200 characters
     *  - priority must be one of: normal, important, critical
     *  - slot fields are all-or-nothing (1 or 2 out of 3 set is invalid)
     *  - interval_minutes must be one of the allowed values when present
     *  - slot_end must be strictly after slot_start when both are set
     *
     * @param  ParsedTaskRow $row
     * @return ParsedTaskRow  Same instance with status and errors mutated.
     */
    private function validateRow(ParsedTaskRow $row): ParsedTaskRow
    {
        $errors = [];

        // Name
        if ($row->name === '') {
            $errors[] = 'name is required';
        } elseif (mb_strlen($row->name) > 200) {
            $errors[] = 'name must not exceed 200 characters';
        }

        // Priority
        if (! in_array($row->priority, self::VALID_PRIORITIES, true)) {
            $errors[] = 'priority must be one of: normal, important, critical';
        }

        // Slot fields: either all three must be set or none.
        $slotSet = count(array_filter([
            $row->slotStartTime,
            $row->slotEndTime,
            $row->slotIntervalMinutes,
        ]));

        if ($slotSet > 0 && $slotSet < 3) {
            $errors[] = 'slot_start, slot_end and interval_minutes must all be provided together';
        }

        // Interval whitelist
        if ($row->slotIntervalMinutes !== null
            && ! in_array($row->slotIntervalMinutes, self::VALID_INTERVALS, true)
        ) {
            $errors[] = 'interval_minutes must be one of: 15, 30, 45, 60, 90, 120';
        }

        // slot_end > slot_start
        if ($row->slotStartTime !== null && $row->slotEndTime !== null) {
            try {
                $start = Carbon::createFromFormat('H:i', $row->slotStartTime);
                $end   = Carbon::createFromFormat('H:i', $row->slotEndTime);
                if ($start && $end && $end->lte($start)) {
                    $errors[] = 'slot_end must be strictly after slot_start';
                }
            } catch (\Throwable) {
                $errors[] = 'slot_start and slot_end must be valid times in H:i format';
            }
        }

        $row->status = empty($errors) ? 'ok' : 'invalid';
        $row->errors = $errors;

        return $row;
    }

    /**
     * Resolves a category name to a category id, creating the record when missing.
     *
     * Lookup is case-insensitive. An in-memory cache avoids redundant queries
     * when the same category name appears multiple times within one import run.
     *
     * New categories receive the first colour token not yet used in this event.
     * Falls back to 'gray' when all 11 tokens are taken.
     *
     * @param  int    $eventId
     * @param  string $name      Raw category name from the CSV.
     * @param  int    $createdBy
     * @param  array  $cache     In-memory cache, passed by reference.
     * @return int    Category id.
     */
    private function findOrCreateCategory(
        int $eventId,
        string $name,
        int $createdBy,
        array &$cache
    ): int {
        $key = strtolower(trim($name));

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $existing = EventTaskCategory::where('event_id', $eventId)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if ($existing) {
            $cache[$key] = $existing->id;
            return $existing->id;
        }

        $usedColors = EventTaskCategory::where('event_id', $eventId)->pluck('color')->toArray();
        $freeColors = array_values(array_diff(self::CATEGORY_COLORS, $usedColors));
        $color      = $freeColors[0] ?? 'gray';

        $category = EventTaskCategory::create([
            'event_id'   => $eventId,
            'name'       => trim($name),
            'color'      => $color,
            'created_by' => $createdBy,
        ]);

        $cache[$key] = $category->id;

        return $category->id;
    }

    /**
     * Strips the UTF-8 BOM (\xEF\xBB\xBF) from the beginning of a string.
     *
     * Excel on Windows prepends this marker to every UTF-8 CSV export.
     *
     * @param  string $content
     * @return string
     */
    private function stripBom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF")
            ? substr($content, 3)
            : $content;
    }

    /**
     * Detects the field delimiter of a CSV line by counting occurrences.
     *
     * German Excel locales default to semicolon; international locales to comma.
     * Falls back to comma when counts are equal.
     *
     * @param  string $line The header line of the CSV.
     * @return non-empty-string ',' | ';'
     */
    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
