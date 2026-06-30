<?php

declare(strict_types=1);

namespace Modules\Members\Http\Controllers;

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

/**
 * Handles member CRUD operations.
 *
 * This controller has no knowledge of other modules.
 * Extensions (e.g. family management by YouthClubMode, custom fields)
 * are injected through view composers and the hook extension-point system.
 *
 * Validation is fully delegated to StoreMemberRequest, UpdateMemberRequest,
 * and UpdatePhotoRequest.
 */
class MemberController extends Controller
{
    /**
     * @param  CustomFieldRepository $cfRepository
     */
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    /**
     * Renders the paginated, filterable member list with the JS data bridge.
     *
     * Supported query parameters:
     *   q         string  Full-text search on first_name / last_name
     *   status    string  Filter by status: active | inactive
     *   eligible  string  Filter by playing eligibility: 1 = eligible, 0 = not eligible
     *
     * @param  Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Member::query();

        // Full-text search across first and last name
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->input('q') . '%')
                  ->orWhere('last_name',  'like', '%' . $request->input('q') . '%');
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Playing eligibility filter (derived from eligible_to_play_date column)
        //   '1' → date is set AND is today or in the past (eligible)
        //   '0' → date is missing OR is still in the future (not eligible)
        if ($request->filled('eligible')) {
            if ($request->input('eligible') === '1') {
                $query->whereNotNull('eligible_to_play_date')
                      ->whereDate('eligible_to_play_date', '<=', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('eligible_to_play_date')
                      ->orWhereDate('eligible_to_play_date', '>', now());
                });
            }
        }

        $members = $query->orderBy('last_name')->paginate(25)->withQueryString();

        // Build JS data bridge for members-modal.js.
        // Note: no fn()/arrow functions in @json() – must use manual foreach loops.
        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = [
                'id'                    => $m->id,
                'first_name'            => $m->first_name,
                'last_name'             => $m->last_name,
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

        // Custom fields (graceful: returns empty arrays when CustomFields module is not installed)
        $cf             = $this->cfRepository->loadForObjectType('member');
        $memberCfDefs   = $cf['defs'];
        $memberCfValues = $cf['values'];

        return view('members::index', compact(
            'members', 'membersJs', 'memberCfDefs', 'memberCfValues'
        ));
    }

    /**
     * Creates a new member from validated form data.
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
     * Updates an existing member from validated form data.
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
     * Replaces the member's profile photo.
     *
     * Deletes the old file from storage before writing the new one.
     * Validation is fully delegated to UpdatePhotoRequest.
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
     * Soft-deletes a member and removes their profile photo from storage.
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
     * Extracts and normalises the scalar member fields from the validated data array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepareData(array $validated): array
    {
        return [
            'first_name'            => $validated['first_name'],
            'last_name'             => $validated['last_name'],
            'gender'                => $validated['gender'] ?? null,
            'date_of_birth'         => $validated['date_of_birth'] ?? null,
            'status'                => $validated['status'],
            'eligible_to_play_date' => $validated['eligible_to_play_date'] ?? null,
        ];
    }

    /**
     * Handles profile image upload: deletes the old file and stores the new one.
     * Returns the data array unchanged when no file is present in the request.
     *
     * @param  Request               $request
     * @param  array<string, mixed>  $data
     * @param  Member|null           $member
     * @return array<string, mixed>
     */
    private function handleImageUpload(Request $request, array $data, ?Member $member = null): array
    {
        if (!$request->hasFile('profile_image')) {
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
