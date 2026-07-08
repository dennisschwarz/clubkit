<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Members\Models\Member;
use Modules\Teams\Http\Requests\StoreTeamMemberRequest;
use Modules\Teams\Http\Requests\StoreTeamRequest;
use Modules\Teams\Http\Requests\UpdateTeamRequest;
use Modules\Teams\Models\Team;
use Modules\Teams\Services\TeamMemberSyncService;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Handles team CRUD and membership management.
 *
 * Allowed sort fields (via ?sort=... | ?sort=-...):
 *   name (default ASC), season, league, age_class, is_active, is_competition
 *
 * Membership assignment has three entry points, all using TeamMemberSyncService:
 *   syncRoster()      → PUT /{team}/members/sync   (Dual Listbox modal on index page)
 *   syncMemberTeams() → PUT /member/{member}/sync  (Team tab in member modal, AJAX)
 *   addMember()       → POST /{team}/members        (Single-add with squad number on detail page)
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly CustomFieldRepository  $cfRepository,
        private readonly TeamMemberSyncService  $syncService,
    ) {}

    /**
     * Display the full team list with available-member maps and JS data bridge.
     *
     * @return View
     */
    public function index(): View
    {
        $teams = QueryBuilder::for(Team::class)
            ->withCount('members')
            ->with(['members' => function ($q) {
                $q->select('members.id', 'first_name', 'last_name', 'eligible_to_play_date')
                  ->orderBy('members.last_name');
            }])
            ->allowedSorts('name', 'season', 'league', 'age_class', 'is_active', 'is_competition')
            ->defaultSort('name')
            ->get();

        $allMembers = Member::select('id', 'first_name', 'last_name', 'eligible_to_play_date')
            ->orderBy('last_name')
            ->get();

        // Pre-compute available members per team to avoid N+1 queries in the view.
        $availableByTeam = [];
        foreach ($teams as $t) {
            $inTeamIds = [];
            foreach ($t->members as $m) {
                $inTeamIds[$m->id] = true;
            }
            $available = [];
            foreach ($allMembers as $m) {
                if (isset($inTeamIds[$m->id])) {
                    continue;
                }
                if ($t->eligible_only && ! $m->eligible_to_play) {
                    continue;
                }
                $available[] = $m;
            }
            $availableByTeam[$t->id] = collect($available);
        }

        // JS data bridge for teams-modal.js — foreach only, no arrow functions.
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

        // Roster data for Dual Listbox modal (teamId → [{id, name}, ...]).
        $rosterByTeamJs = [];
        foreach ($teams as $t) {
            $roster = [];
            foreach ($t->members as $m) {
                $roster[] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
            }
            $rosterByTeamJs[$t->id] = $roster;
        }

        // Available-member data for Dual Listbox modal.
        $availableByTeamJs = [];
        foreach ($availableByTeam as $teamId => $members) {
            $available = [];
            foreach ($members as $m) {
                $available[] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
            }
            $availableByTeamJs[$teamId] = $available;
        }

        $cf           = $this->cfRepository->loadForObjectType('team');
        $teamCfDefs   = $cf['defs'];
        $teamCfValues = $cf['values'];

        return view('teams::index', compact(
            'teams', 'teamsJs', 'availableByTeam',
            'rosterByTeamJs', 'availableByTeamJs',
            'teamCfDefs', 'teamCfValues',
        ));
    }

    /**
     * Display the team detail page with current roster and available-members list.
     *
     * @param  Team $team
     * @return View
     */
    public function show(Team $team): View
    {
        $team->load('members');

        $canAddMembers    = $team->is_active;
        $availableMembers = collect();
        $availableJs      = [];

        if ($canAddMembers) {
            $alreadyInTeam = $team->members->pluck('id');

            $availableMembers = Member::orderBy('last_name')
                ->whereNotIn('id', $alreadyInTeam)
                ->when($team->eligible_only, function ($q) {
                    $q->whereNotNull('eligible_to_play_date')
                      ->whereDate('eligible_to_play_date', '<=', now());
                })
                ->get();

            foreach ($availableMembers as $m) {
                $availableJs[$m->id] = $m->toJsOption();
            }
        }

        return view('teams::show', compact('team', 'canAddMembers', 'availableMembers', 'availableJs'));
    }

    /**
     * Store a newly created team.
     *
     * @param  StoreTeamRequest $request
     * @return RedirectResponse
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $validated               = $request->validated();
        $validated['created_by'] = $request->user()->id;
        Team::create($validated);

        return redirect()->route('teams.index')->with('success', __('teams.flash.created', ['name' => $validated['name']]));
    }

    /**
     * Update an existing team.
     *
     * @param  UpdateTeamRequest $request
     * @param  Team              $team
     * @return RedirectResponse
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()->route('teams.index')->with('success', __('teams.flash.updated', ['name' => $team->name]));
    }

    /**
     * Delete a team.
     *
     * @param  Team $team
     * @return RedirectResponse
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', __('teams.flash.deleted', ['name' => $team->name]));
    }

    /**
     * Sync a team's entire roster from the Dual Listbox modal.
     * Delegates to TeamMemberSyncService::syncRoster().
     *
     * @param  Request $request
     * @param  Team    $team
     * @return RedirectResponse
     */
    public function syncRoster(Request $request, Team $team): RedirectResponse
    {
        if (! $team->is_active) {
            return back()->with('error', 'Inaktive Teams können nicht bearbeitet werden.');
        }

        $memberIds = array_filter(array_map('intval', $request->input('member_ids', [])));

        $result = $this->syncService->syncRoster($team, $memberIds);

        return back()->with('success', sprintf(
            'Kader gespeichert: %d hinzugefügt, %d entfernt.',
            $result['attached'],
            $result['detached'],
        ));
    }

    /**
     * Sync a member's team assignments from the Team tab in the member modal.
     * Accepts both JSON (AJAX) and form POST. Returns JSON when the request
     * sends an Accept: application/json header.
     * Delegates to TeamMemberSyncService::syncMemberTeams().
     *
     * @param  Request $request
     * @param  Member  $member
     * @return JsonResponse|RedirectResponse
     */
    public function syncMemberTeams(Request $request, Member $member): JsonResponse|RedirectResponse
    {
        $teamIds = array_filter(array_map('intval', $request->input('team_ids', [])));

        $result = $this->syncService->syncMemberTeams($member, $teamIds);

        if ($request->wantsJson()) {
            return response()->json([
                'added'   => $result['added'],
                'removed' => $result['removed'],
            ]);
        }

        return back()->with('success', sprintf(
            'Teams gespeichert: %d hinzugefügt, %d entfernt.',
            $result['added'],
            $result['removed'],
        ));
    }

    /**
     * Add a single member to a team roster (with optional squad number).
     * Used on the team detail page (teams::show).
     * Enforces active-team and eligibility rules before attaching.
     *
     * @param  StoreTeamMemberRequest $request
     * @param  Team                   $team
     * @return RedirectResponse
     */
    public function addMember(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        if (! $team->is_active) {
            return back()->with('error', 'Inaktive Teams können keine Mitglieder aufnehmen.');
        }

        $memberId = $request->validated()['member_id'];
        $member   = Member::findOrFail($memberId);

        if ($team->eligible_only && ! $member->eligible_to_play) {
            return back()->with('error', 'Dieses Mitglied ist nicht spielberechtigt.');
        }

        if ($team->members()->where('member_id', $memberId)->exists()) {
            return back()->with('error', 'Mitglied ist bereits im Team.');
        }

        $team->members()->attach($memberId, [
            'squad_number' => $request->validated()['squad_number'] ?? null,
            'joined_at'    => now(),
        ]);

        // Manual activity log: team_member pivot does not use LogsActivity trait.
        activity()->useLog('teams')
            ->on($team)
            ->withProperties(['member_id' => $memberId, 'member_name' => $member->full_name])
            ->log('member_added');

        return back()->with('success', 'Mitglied hinzugefügt.');
    }

    /**
     * Remove a member from a team roster.
     *
     * @param  Team $team
     * @param  int  $memberId
     * @return RedirectResponse
     */
    public function removeMember(Team $team, int $memberId): RedirectResponse
    {
        $member = Member::findOrFail($memberId);

        $team->members()->detach($memberId);

        // Manual activity log: team_member pivot does not use LogsActivity trait.
        activity()->useLog('teams')
            ->on($team)
            ->withProperties(['member_id' => $memberId, 'member_name' => $member->full_name])
            ->log('member_removed');

        return back()->with('success', 'Mitglied entfernt.');
    }
}