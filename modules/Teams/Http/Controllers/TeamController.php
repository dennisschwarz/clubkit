<?php

declare(strict_types=1);

namespace Modules\Teams\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Members\Models\Member;
use Modules\Teams\Http\Requests\StoreTeamMemberRequest;
use Modules\Teams\Http\Requests\StoreTeamRequest;
use Modules\Teams\Http\Requests\UpdateTeamRequest;
use Modules\Teams\Models\Team;

/**
 * Handles team CRUD and membership management.
 *
 * Structural changes to the team record are logged automatically via the
 * LogsActivity trait on the Team model. Pivot changes (adding/removing members)
 * have no first-class Eloquent model, so they are logged manually here via
 * the activity() helper with log_name 'teams'.
 *
 * Validation is fully delegated to StoreTeamRequest, UpdateTeamRequest,
 * and StoreTeamMemberRequest.
 */
class TeamController extends Controller
{
    /**
     * @param  CustomFieldRepository $cfRepository
     */
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Renders the paginated team list with the JS data bridge.
     *
     * For each team, pre-computes the list of members who can still be added
     * (not already in the team, respects eligible_only constraint) so that
     * the "add member" modal dropdown can be populated without extra requests.
     *
     * @return View
     */
    public function index(): View
    {
        $teams = Team::withCount('members')
            ->with(['members' => function ($q) {
                $q->select('members.id', 'first_name', 'last_name', 'eligible_to_play_date')
                  ->orderBy('members.last_name');
            }])
            ->orderBy('name')
            ->get();

        $allMembers = Member::select('id', 'first_name', 'last_name', 'eligible_to_play_date')
            ->orderBy('last_name')
            ->get();

        // Build per-team available-member lists for the modal dropdowns
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

        // Build JS data bridge for teams-modal.js
        // No fn()/arrow functions in @json() – must use manual foreach loops
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

        // Custom fields (graceful: returns empty arrays when CustomFields module is not installed)
        $cf           = $this->cfRepository->loadForObjectType('team');
        $teamCfDefs   = $cf['defs'];
        $teamCfValues = $cf['values'];

        return view('teams::index', compact(
            'teams', 'teamsJs', 'availableByTeam', 'teamCfDefs', 'teamCfValues'
        ));
    }

    /**
     * Renders the team detail page with the member roster and add-member form.
     *
     * Only builds the available-member list when the team is active;
     * inactive teams cannot accept new members.
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
     * Creates a new team.
     *
     * @param  StoreTeamRequest $request
     * @return RedirectResponse
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $validated               = $request->validated();
        $validated['created_by'] = $request->user()->id;
        Team::create($validated);

        return redirect()->route('teams.index')->with('success', 'Team angelegt.');
    }

    /**
     * Updates an existing team's properties.
     *
     * @param  UpdateTeamRequest $request
     * @param  Team              $team
     * @return RedirectResponse
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()->route('teams.index')->with('success', 'Team aktualisiert.');
    }

    /**
     * Deletes a team (and detaches all members via cascade on the pivot).
     *
     * @param  Team $team
     * @return RedirectResponse
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Team gelöscht.');
    }

    /**
     * Adds a member to a team via the team_member pivot.
     *
     * Guards:
     *   - Team must be active
     *   - Member must satisfy the team's eligible_only constraint
     *   - Member must not already be in the team
     *
     * The pivot change is logged manually (no LogsActivity on the pivot table).
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

        $validated = $request->validated();
        $member    = Member::findOrFail($validated['member_id']);

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

        // Manual pivot log: the team_member pivot has no LogsActivity
        activity('teams')
            ->performedOn($team)
            ->withProperties([
                'member_id'    => $member->id,
                'member_name'  => $member->last_name . ', ' . $member->first_name,
                'squad_number' => $validated['squad_number'] ?? null,
            ])
            ->event('member_added')
            ->log('member_added');

        return back()->with('success', $member->last_name . ', ' . $member->first_name . ' hinzugefügt.');
    }

    /**
     * Removes a member from a team via the team_member pivot.
     *
     * The pivot change is logged manually (no LogsActivity on the pivot table).
     *
     * @param  Team   $team
     * @param  Member $member
     * @return RedirectResponse
     */
    public function removeMember(Team $team, Member $member): RedirectResponse
    {
        $team->members()->detach($member->id);

        // Manual pivot log: the team_member pivot has no LogsActivity
        activity('teams')
            ->performedOn($team)
            ->withProperties([
                'member_id'   => $member->id,
                'member_name' => $member->last_name . ', ' . $member->first_name,
            ])
            ->event('member_removed')
            ->log('member_removed');

        return back()->with('success', $member->last_name . ' aus dem Team entfernt.');
    }
}
