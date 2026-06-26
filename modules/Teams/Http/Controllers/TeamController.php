<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

class TeamController extends Controller
{
    /** Erlaubte Teamfarben (Schlüssel der CSS-Palette) */
    private const ALLOWED_COLORS = [
        'blue', 'navy', 'green', 'teal', 'red',
        'orange', 'amber', 'purple', 'pink', 'slate',
    ];

    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    public function index(): View
    {
        // Teams mit Mitglieder-Anzahl + geladenen Mitgliedern (für Accordion)
        // eligible_to_play_date statt eligible_to_play – Spalte wurde per Migration umbenannt.
        // Der Accessor Member::getEligibleToPlayAttribute() leitet den bool daraus ab.
        $teams = Team::withCount('members')
            ->with(['members' => function ($q) {
                $q->select('members.id', 'first_name', 'last_name', 'eligible_to_play_date')
                  ->orderBy('members.last_name');
            }])
            ->orderBy('name')
            ->get();

        // Alle Mitglieder – für "Hinzufügen"-Select im Accordion
        $allMembers = Member::select('id', 'first_name', 'last_name', 'eligible_to_play_date')
            ->orderBy('last_name')
            ->get();

        // Verfügbare Mitglieder pro Team (nicht im Kader + Spielberechtigungsfilter)
        $availableByTeam = [];
        foreach ($teams as $t) {
            $inTeamIds = [];
            foreach ($t->members as $m) {
                $inTeamIds[$m->id] = true;
            }
            $available = [];
            foreach ($allMembers as $m) {
                if (isset($inTeamIds[$m->id])) continue;

                // eligible_to_play nutzt den Accessor → prüft eligible_to_play_date <= heute
                if ($t->eligible_only && ! $m->eligible_to_play) continue;

                $available[] = $m;
            }
            $availableByTeam[$t->id] = collect($available);
        }

        // Data Bridge – kein fn() / Arrow-Functions in @json()
        $teamsJs = [];
        foreach ($teams as $t) {
            $teamsJs[$t->id] = [
                'id'             => $t->id,
                'name'           => $t->name,
                'color'          => $t->color,
                'is_competition' => $t->is_competition,
                'eligible_only'  => $t->eligible_only,
                'season'         => $t->season,
                'league'         => $t->league,
                'age_class'      => $t->age_class,
                'is_active'      => $t->is_active,
            ];
        }

        // Eigene Felder (graceful: leer wenn CustomFields nicht installiert)
        $cf           = $this->cfRepository->loadForObjectType('team');
        $teamCfDefs   = $cf['defs'];
        $teamCfValues = $cf['values'];

        return view('teams::index', compact(
            'teams', 'teamsJs', 'availableByTeam', 'teamCfDefs', 'teamCfValues'
        ));
    }

    public function show(Team $team): View
    {
        $team->load('members');

        $canAddMembers    = $team->is_active;
        $availableMembers = collect();
        $availableJs      = [];

        if ($canAddMembers) {
            $alreadyInTeam = $team->members->pluck('id');

            // eligible_only-Filter: eligible_to_play_date <= heute statt WHERE eligible_to_play = 1
            // (Accessor-Logik muss in der DB-Query nachgebaut werden)
            $availableMembers = Member::orderBy('last_name')
                ->whereNotIn('id', $alreadyInTeam)
                ->when($team->eligible_only, function ($q) {
                    $q->whereNotNull('eligible_to_play_date')
                      ->whereDate('eligible_to_play_date', '<=', now());
                })
                ->get();

            foreach ($availableMembers as $m) {
                $availableJs[$m->id] = [
                    'id'   => $m->id,
                    'name' => $m->last_name . ', ' . $m->first_name,
                ];
            }
        }

        return view('teams::show', compact('team', 'canAddMembers', 'availableMembers', 'availableJs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated               = $this->validateTeam($request);
        $validated['created_by'] = auth()->id();
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
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Team gelöscht.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        if (! $team->is_active) {
            return back()->with('error', 'Inaktive Teams können keine Mitglieder aufnehmen.');
        }

        $validated = $request->validate([
            'member_id'    => ['required', 'exists:members,id'],
            'squad_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $member = Member::findOrFail($validated['member_id']);

        if (! $team->canAddMember($member)) {
            return back()->with(
                'error',
                $member->last_name . ' ist nicht spielberechtigt und darf diesem Team nicht hinzugefügt werden.'
            );
        }

        if ($team->members()->where('member_id', $member->id)->exists()) {
            return back()->with('error', $member->last_name . ' ist bereits in diesem Team.');
        }

        $team->members()->attach($member->id, [
            'squad_number' => $validated['squad_number'] ?? null,
        ]);

        return back()->with('success', $member->last_name . ', ' . $member->first_name . ' hinzugefügt.');
    }

    public function removeMember(Team $team, Member $member): RedirectResponse
    {
        $team->members()->detach($member->id);

        return back()->with('success', $member->last_name . ' aus dem Team entfernt.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function validateTeam(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'color'          => ['nullable', Rule::in(self::ALLOWED_COLORS)],
            'is_competition' => ['nullable', 'boolean'],
            'eligible_only'  => ['nullable', 'boolean'],
            'season'         => ['nullable', 'string', 'max:20'],
            'league'         => ['nullable', 'string', 'max:100'],
            'age_class'      => ['nullable', 'string', 'max:50'],
            'is_active'      => ['nullable', 'boolean'],
        ]);
    }
}
