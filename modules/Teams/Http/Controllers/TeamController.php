<?php

namespace Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::withCount('members')->orderBy('name')->get();

        // JS-Daten: foreach, keine Closures
        $teamsJs = [];
        foreach ($teams as $t) {
            $teamsJs[$t->id] = [
                'id'        => $t->id,
                'name'      => $t->name,
                'season'    => $t->season,
                'league'    => $t->league,
                'age_class' => $t->age_class,
                'is_active' => $t->is_active,
            ];
        }

        return view('teams::index', compact('teams', 'teamsJs'));
    }

    public function show(Team $team): View
    {
        $team->load('members');

        // Nur spielberechtigte Mitglieder, die noch nicht in diesem Team sind
        $eligibleMembers = Member::where('eligible_to_play', true)
            ->whereNotIn('id', $team->members->pluck('id'))
            ->orderBy('last_name')
            ->get();

        $eligibleJs = [];
        foreach ($eligibleMembers as $m) {
            $eligibleJs[$m->id] = [
                'id'   => $m->id,
                'name' => $m->last_name . ', ' . $m->first_name,
            ];
        }

        return view('teams::show', compact('team', 'eligibleMembers', 'eligibleJs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTeam($request);
        Team::create($validated);

        return redirect()->route('teams.index')->with('success', 'Team angelegt.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $this->validateTeam($request);
        $team->update($validated);

        return redirect()->route('teams.index')->with('success', 'Team aktualisiert.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete(); // Pivot-Einträge werden durch cascade gelöscht

        return redirect()->route('teams.index')->with('success', 'Team gelöscht.');
    }

    /**
     * Mitglied zu Team hinzufügen
     */
    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'member_id'    => ['required', 'exists:members,id'],
            'squad_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        // Nur spielberechtigte Mitglieder erlaubt
        $member = Member::findOrFail($validated['member_id']);

        if (!$member->eligible_to_play) {
            return back()->with('error', 'Nur spielberechtigte Mitglieder können einem Team hinzugefügt werden.');
        }

        // Bereits im Team?
        if ($team->members()->where('member_id', $member->id)->exists()) {
            return back()->with('error', $member->last_name . ' ist bereits in diesem Team.');
        }

        $team->members()->attach($member->id, [
            'squad_number' => $validated['squad_number'] ?? null,
        ]);

        return back()->with('success', $member->last_name . ', ' . $member->first_name . ' hinzugefügt.');
    }

    /**
     * Mitglied aus Team entfernen
     */
    public function removeMember(Team $team, Member $member): RedirectResponse
    {
        $team->members()->detach($member->id);

        return back()->with('success', $member->last_name . ' aus dem Team entfernt.');
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private function validateTeam(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'season'    => ['nullable', 'string', 'max:20'],
            'league'    => ['nullable', 'string', 'max:100'],
            'age_class' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
