<?php

declare(strict_types=1);

namespace Modules\Members\Http\Controllers;

use App\Filters\EligibleFilter;
use App\Filters\NameSearchFilter;
use App\Repositories\CustomFieldRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Members\Http\Requests\StoreMemberRequest;
use Modules\Members\Http\Requests\UpdateMemberRequest;
use Modules\Members\Http\Requests\UpdatePhotoRequest;
use Modules\Members\Models\Member;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Handles member CRUD operations with filterable, paginated list.
 *
 * Allowed filters (via ?filter[x]=...):
 *   filter[q]        string  — searches first_name and last_name
 *   filter[status]   string  — exact match: active | inactive
 *   filter[eligible] integer — 1 = eligible to play | 0 = not eligible
 *
 * Allowed sort fields (via ?sort=... | ?sort=-...):
 *   last_name, first_name, date_of_birth, pass_number, eligible_to_play_date, status
 *
 * allowedFilters() and allowedSorts() accept variadic args — NO array wrapper.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Display the paginated, filterable member list with JS data bridge.
     *
     * @param  Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $members = QueryBuilder::for(Member::class)
            ->allowedFilters(
                AllowedFilter::custom('q',        new NameSearchFilter()),
                AllowedFilter::exact('status'),
                AllowedFilter::custom('eligible', new EligibleFilter()),
            )
            ->allowedSorts(
                'last_name',
                'first_name',
                'date_of_birth',
                'pass_number',
                'eligible_to_play_date',
                'status',
            )
            ->defaultSort('last_name')
            ->paginate(25)
            ->withQueryString();

        // JS data bridge for members-modal.js.
        // Never use fn()/arrow functions inside @json() — use foreach only.
        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = [
                'id'                    => $m->id,
                'first_name'            => $m->first_name,
                'last_name'             => $m->last_name,
                'pass_number'           => $m->pass_number,
                'gender'                => $m->gender,
                'date_of_birth'         => $m->date_of_birth?->format('Y-m-d'),
                'status'                => $m->status,
                'eligible_to_play'      => (bool) $m->eligible_to_play,
                'eligible_to_play_date' => $m->eligible_to_play_date?->format('Y-m-d'),
                'profile_image'         => $m->profile_image
                    ? asset('storage/' . $m->profile_image)
                    : null,
            ];
        }

        $cf             = $this->cfRepository->loadForObjectType('member');
        $memberCfDefs   = $cf['defs'];
        $memberCfValues = $cf['values'];

        return view('members::index', compact(
            'members', 'membersJs', 'memberCfDefs', 'memberCfValues'
        ));
    }

    /**
     * Store a newly created member.
     *
     * @param  StoreMemberRequest $request
     * @return RedirectResponse
     */
    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $data               = $this->prepareData($request->validated());
        $data['created_by'] = $request->user()->id;
        $data               = $this->handleImageUpload($request, $data);

        Member::create($data);

        return redirect()->route('members.index')->with('success', 'Mitglied erstellt.');
    }

    /**
     * Update an existing member.
     *
     * @param  UpdateMemberRequest $request
     * @param  Member              $member
     * @return RedirectResponse
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $data = $this->handleImageUpload($request, $data, $member);

        $member->update($data);

        return redirect()->route('members.index')->with('success', 'Mitglied aktualisiert.');
    }

    /**
     * Replace the profile photo for an existing member.
     *
     * @param  UpdatePhotoRequest $request
     * @param  Member             $member
     * @return RedirectResponse
     */
    public function updatePhoto(UpdatePhotoRequest $request, Member $member): RedirectResponse
    {
        if ($member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }

        $path = $request->file('profile_image')->store('members', 'public');
        $member->update(['profile_image' => $path]);

        return redirect()->route('members.index')
            ->with('success', 'Foto für „' . $member->last_name . ', ' . $member->first_name . '" gespeichert.');
    }

    /**
     * Remove a member and delete their profile photo if present.
     *
     * @param  Member $member
     * @return RedirectResponse
     */
    public function destroy(Member $member): RedirectResponse
    {
        if ($member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }
        $member->delete();

        return redirect()->route('members.index')->with('success', 'Mitglied gelöscht.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Map validated request data to the Member fillable fields.
     *
     * @param  array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function prepareData(array $validated): array
    {
        return [
            'first_name'            => $validated['first_name'],
            'last_name'             => $validated['last_name'],
            'pass_number'           => $validated['pass_number'] ?? null,
            'gender'                => $validated['gender'] ?? null,
            'date_of_birth'         => $validated['date_of_birth'] ?? null,
            'status'                => $validated['status'],
            'eligible_to_play_date' => $validated['eligible_to_play_date'] ?? null,
        ];
    }

    /**
     * Store a newly uploaded profile image and delete the previous one.
     * Returns the data array unchanged if no file was uploaded.
     *
     * @param  Request              $request
     * @param  array<string, mixed> $data
     * @param  Member|null          $member  Existing member whose old image should be removed.
     * @return array<string, mixed>
     */
    private function handleImageUpload(Request $request, array $data, ?Member $member = null): array
    {
        if (! $request->hasFile('profile_image')) {
            return $data;
        }

        if ($member?->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }

        $data['profile_image'] = $request->file('profile_image')
            ->store('members', 'public');

        return $data;
    }
}
