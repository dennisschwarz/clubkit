<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * Zentrales Hook-System für ClubKit-Module.
 *
 * Jedes Modul kann sich an definierten Extension Points registrieren,
 * ohne dass das Host-Modul von ihm wissen muss. Das ermöglicht vollständige
 * Modul-Unabhängigkeit – ein Modul kann deinstalliert werden, ohne den Host zu brechen.
 *
 * ─── Registrierung (in ServiceProvider::boot()) ───────────────────────────────
 *
 *   app('ck.hooks')->register(
 *       'member.modal.tabs',              // Extension Point
 *       'youth-club-mode::member-tab',    // Laravel View-Name
 *       20                                // Priorität (optional, Standard: 10)
 *   );
 *
 * ─── Nutzung in Blade ─────────────────────────────────────────────────────────
 *
 *   @ckHook('member.modal.tabs')
 *
 * Alle aktuellen View-Variablen werden automatisch an die Hook-Views übergeben.
 *
 * ─── Definierte Extension Points ──────────────────────────────────────────────
 *
 *   member.table.header    → Extra <th>-Elemente in der Mitglieder-Tabelle
 *   member.table.row       → Extra <td>-Elemente pro Zeile ($member verfügbar)
 *   member.modal.tabs      → Zusätzliche Tab-Buttons im Member-Modal
 *   member.modal.sections  → Zusätzliche Sections/Tab-Inhalte im Member-Modal
 *   member.page.scripts    → Zusätzliche <script>-Tags am Ende der Mitglieder-Seite
 */
class HookRegistry
{
    /** @var array<string, list<array{view: string, priority: int}>> */
    protected array $hooks = [];

    /**
     * Hook-View für einen Extension Point registrieren.
     *
     * @param  string  $point     Extension Point (z. B. 'member.modal.tabs')
     * @param  string  $view      Laravel View-Name (z. B. 'youth-club-mode::member-tab')
     * @param  int     $priority  Reihenfolge: kleiner = weiter oben (Standard: 10)
     */
    public function register(string $point, string $view, int $priority = 10): void
    {
        $this->hooks[$point][] = ['view' => $view, 'priority' => $priority];
    }

    /**
     * Alle für einen Extension Point registrierten View-Namen zurückgeben.
     * Sortiert nach Priorität (aufsteigend).
     *
     * @return list<string>
     */
    public function get(string $point): array
    {
        $hooks = $this->hooks[$point] ?? [];
        usort($hooks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        return array_column($hooks, 'view');
    }

    /**
     * Prüft ob für einen Extension Point mindestens ein Hook registriert ist.
     */
    public function has(string $point): bool
    {
        return !empty($this->hooks[$point]);
    }
}
