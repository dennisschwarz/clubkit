<?php

declare(strict_types=1);

namespace Modules\Management\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Modules\Management\Http\Requests\EventTaskImportRequest;
use Modules\Management\Http\Requests\PreviewEventTaskImportRequest;
use Modules\Management\Services\EventTaskImportService;
use Modules\Management\Services\ParsedTaskRow;

/**
 * Handles CSV import of event tasks (both regular tasks and Einsatzplan tasks).
 *
 * Two-phase workflow:
 *   preview()  — accepts a CSV file, returns parsed rows as JSON.
 *                The browser renders the interactive preview from this response.
 *   execute()  — accepts the user-confirmed JSON payload, writes to the DB.
 *   template() — returns a downloadable CSV template file.
 *
 * Security: permission:events.manage enforced at the route level.
 * IDOR protection: the Event route model binding scopes all operations to one event.
 */
class EventTaskImportController extends Controller
{
    public function __construct(
        private readonly EventTaskImportService $service,
    ) {}

    /**
     * Parses an uploaded CSV file and returns a JSON preview.
     *
     * The response contains every row with its status ('ok' | 'invalid'),
     * errors list, and is_slot_task flag so the browser can render the
     * interactive category-grouped preview without server round-trips.
     *
     * @param  PreviewEventTaskImportRequest $request
     * @param  Event                         $event
     * @return JsonResponse
     */
    public function preview(PreviewEventTaskImportRequest $request, Event $event): JsonResponse
    {
        $contents = $request->file('csv')->get();
        $rows     = $this->service->parseCsv($contents);

        $serialised   = array_map(static fn (ParsedTaskRow $r) => $r->toArray(), $rows);
        $validCount   = count(array_filter($rows, static fn ($r) => $r->status === 'ok'));
        $invalidCount = count($rows) - $validCount;

        return response()->json([
            'rows'          => $serialised,
            'valid_count'   => $validCount,
            'invalid_count' => $invalidCount,
        ]);
    }

    /**
     * Creates tasks from the confirmed JSON payload (user-selected rows).
     *
     * The browser sends only the rows the user has checked and optionally
     * repositioned via drag-and-drop. Each task object is converted to a
     * ParsedTaskRow DTO; the service creates categories and tasks inside one
     * DB transaction.
     *
     * Returns HTTP 200 with {imported: N, skipped: M}.
     *
     * @param  EventTaskImportRequest $request
     * @param  Event                  $event
     * @return JsonResponse
     */
    public function execute(EventTaskImportRequest $request, Event $event): JsonResponse
    {
        $tasks = $request->validated()['tasks'];
        $rows  = [];

        foreach ($tasks as $t) {
            $rows[] = new ParsedTaskRow(
                name:                trim((string) ($t['name'] ?? '')),
                category:            ($t['category'] ?? '') ?: null,
                priority:            (string) ($t['priority'] ?? 'normal'),
                deadline:            ($t['deadline'] ?? '') ?: null,
                notes:               ($t['notes'] ?? '') ?: null,
                slotStartTime:       ($t['slot_start_time'] ?? '') ?: null,
                slotEndTime:         ($t['slot_end_time'] ?? '') ?: null,
                slotIntervalMinutes: isset($t['slot_interval_minutes'])
                    ? (int) $t['slot_interval_minutes']
                    : null,
                slotCapacity: isset($t['slot_capacity'])
                    ? (int) $t['slot_capacity']
                    : null,
                // FormRequest validation already guarantees these rows are well-formed.
                // status 'ok' skips the service's internal validation.
                status: 'ok',
                errors: [],
            );
        }

        $summary = $this->service->execute($event, $request->user()->id, $rows);

        return response()->json($summary);
    }

    /**
     * Returns a downloadable CSV template with header row and two example rows.
     *
     * Row 1: a regular event task (no slot fields).
     * Row 2: an Einsatzplan task (slot fields filled).
     *
     * @param  Event $event  Required by route model binding; unused here.
     * @return Response
     */
    public function template(Event $event): Response
    {
        $lines = [
            'name,category,priority,deadline,notes,slot_start,slot_end,interval_minutes,capacity',
            '"Pizza bestellen","Catering","normal","","3 Tage vorher","","","",""',
            '"Einlass","Freiwillige","normal","","","18:00","22:00","60","2"',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="event-tasks-import-template.csv"',
        ]);
    }
}
