<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * Central extension-point system for ClubKit modules.
 *
 * Each module can register itself at defined extension points without the host
 * module needing any knowledge of it. This enables full module independence:
 * a module can be uninstalled without breaking the host.
 *
 * ─── Registration (inside a ServiceProvider::boot() method) ──────────────────
 *
 *   app('ck.hooks')->register(
 *       'member.modal.tabs',              // extension point
 *       'youth-club-mode::member-tab',    // Laravel view name
 *       20                                // priority (optional, default: 10)
 *   );
 *
 * ─── Usage in Blade ───────────────────────────────────────────────────────────
 *
 *   @ckHook('member.modal.tabs')
 *
 * All variables currently in the view scope are automatically forwarded
 * to hook views. No manual data passing required.
 *
 * ─── Usage with fallback (replaceable section pattern) ────────────────────────
 *
 *   @if(app('ck.hooks')->has('management.function.list'))
 *       @ckHook('management.function.list')
 *   @else
 *       {{-- default rendering when no module extends this point --}}
 *   @endif
 *
 * ─── Defined extension points ─────────────────────────────────────────────────
 *
 * Members (members::index):
 *   member.page.actions    → Buttons next to "+ Mitglied hinzufügen" (e.g. import button)
 *   member.table.header    → Extra <th> elements in the members table
 *   member.table.row       → Extra <td> elements per row ($member available)
 *   member.table.expandrow → [Planned] Expandable row below each member row
 *   member.modal.tabs      → Additional tab buttons in the member modal
 *   member.modal.sections  → Additional tab contents in the member modal
 *   member.page.scripts    → Additional <script> tags at the end of the members page
 *
 * Teams (teams::index):
 *   team.modal.tabs        → Additional tab buttons in the team modal
 *   team.modal.sections    → Additional tab contents in the team modal
 *
 * Management (management::index):
 *   management.function.header.filter    → Team filter form in the functions tab header
 *   management.function.list             → Replaces the default flat function list.
 *                                          Use has() check: if registered, it renders instead
 *                                          of the default flat @include. Receives $functions.
 *   management.function.modal.teams      → Team checkbox block inside the function modal form
 *   management.task.header.filter        → Team filter form in the tasks tab header
 *   management.task.list                 → Replaces the default flat task list (same pattern)
 *   management.task.modal.teams          → Team checkbox block inside the task modal form
 *   management.function.modal.tabs       → Additional tab buttons in the function modal
 *   management.function.modal.sections   → Additional tab contents in the function modal
 *   management.task.modal.tabs           → Additional tab buttons in the task modal
 *   management.task.modal.sections       → Additional tab contents in the task modal
 *   management.page.scripts              → Additional <script> tags at the end of the page
 *
 * Events (events::index + events::show):
 *   event.modal.sections           → Additional sections in the quick-create event modal
 *   event.table.header             → Extra <th> in the events list table
 *   event.table.row                → Extra <td> per row
 *   event.page.scripts             → Additional scripts on the events list page
 *   event.table.teams.header       → <th> for the Teams column (injected by Teams module)
 *   event.table.teams.row          → <td> Teams badges per row (injected by Teams module)
 *   event.table.staffing.row       → Content of the Besetzung cell (injected by Management)
 *   events.show.tasks-panel        → Full tasks section on the detail page (Management)
 *   events.show.teams-panel        → Teams card on the detail page (Teams)
 *   events.show.page.scripts       → Additional scripts on the detail page
 *
 * Admin:
 *   admin.module-settings.sections → Additional sections on the module settings page
 *
 * ─── Registered by ────────────────────────────────────────────────────────────
 *
 *   member.page.actions           → Import (priority 10)
 *   member.*                      → YouthClubMode (priority 20), CustomFields (priority 90)
 *   team.*                        → CustomFields (priority 90)
 *   event.*                       → CustomFields (priority 90)
 *   management.*                  → CustomFields (priority 90)
 *   admin.*                       → CustomFields (priority 20)
 *   management.function.list      → Teams (priority 10)
 *   management.task.list          → Teams (priority 10)
 *   management.function.modal.*   → Teams (priority 10)
 *   management.task.modal.*       → Teams (priority 10)
 *   management.page.scripts       → Teams (priority 10)
 *   events.show.tasks-panel       → Management (priority 10)
 *   event.table.staffing.row      → Management (priority 10)
 *   event.table.teams.*           → Teams (priority 10)
 *   events.show.teams-panel       → Teams (priority 10)
 */
class HookRegistry
{
    /** @var array<string, list<array{view: string, priority: int}>> */
    protected array $hooks = [];

    /**
     * Registers a hook view for a given extension point.
     *
     * @param  string  $point     Extension point identifier (e.g. 'member.modal.tabs')
     * @param  string  $view      Laravel view name (e.g. 'youth-club-mode::member-tab')
     * @param  int     $priority  Render order: lower values render first (default: 10)
     * @return void
     */
    public function register(string $point, string $view, int $priority = 10): void
    {
        $this->hooks[$point][] = ['view' => $view, 'priority' => $priority];
    }

    /**
     * Returns all view names registered for the given extension point,
     * sorted by priority in ascending order.
     *
     * @param  string       $point
     * @return list<string>
     */
    public function get(string $point): array
    {
        $hooks = $this->hooks[$point] ?? [];
        usort($hooks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        return array_column($hooks, 'view');
    }

    /**
     * Returns true when at least one hook is registered for the given extension point.
     *
     * @param  string $point
     * @return bool
     */
    public function has(string $point): bool
    {
        return ! empty($this->hooks[$point]);
    }
}