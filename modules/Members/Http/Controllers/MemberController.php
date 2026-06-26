<?php

declare(strict_types=1);

namespace Modules\Members\Http\Controllers;

use App\Repositories\CustomFieldRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Members\Models\Member;

/**
 * Verwaltet Mitglieder-CRUD.
 *
 * Dieser Controller weiß nichts über andere Module.
 * Erweiterungen (z. B. Familienverwaltung durch YouthClubMode)
 * werden über View Composers und das Hook-System eingebunden.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly CustomFieldRepository $cfRepository
    ) {}

    public function index(Request $request): View
    {
        $query = Member::query();

        // Volltextsuche über Vor- und Nachname
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->input('q') . '%')
                  ->orWhere('last_name',  'like', '%' . $request->input('q') . '%');
            });
        }

        // Status-Filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Spielberechtigungs-Filter
        // eligible_to_play wird aus eligible_to_play_date abgeleitet:
        //   "1" → Datum vorhanden UND <= heute (berechtigt)
        //   "0" → Datum fehlt ODER liegt in der Zukunft (nicht berechtigt)
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

        // JS-Daten aufbereiten – kein fn() / Arrow-Functions in @json()
        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = [
                'id'                    => $m->id,
                'first_name'            => $m->first_name,
                'last_name'             => $m->last_name,
                'gender'                => $m->gender,
                'date_of_birth'         => $m->date_of_birth?->format('Y-m-d'),
                'status'                => $m->status,
                'eligible_to_play'      => (bool) $m->eligible_to_play,         // Accessor-Wert für Anzeige
                'eligible_to_play_date' => $m->eligible_to_play_date?->format('Y-m-d'), // für Formular
                'profile_image'         => $m->profile_image
                    ? asset('storage/' . $m->profile_image)
                    : null,
            ];
        }

        // Eigene Felder (graceful: leer wenn CustomFields nicht installiert)
        $cf             = $this->cfRepository->loadForObjectType('member');
        $memberCfDefs   = $cf['defs'];
        $memberCfValues = $cf['values'];

        return view('members::index', compact(
            'members', 'membersJs', 'memberCfDefs', 'memberCfValues'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated          = $this->validateMember($request);
        $data               = $this->prepareData($validated);
        $data['created_by'] = auth()->id();
        $data               = $this->handleImageUpload($request, $data);

        Member::create($data);

        return redirect()->route('members.index')->with('success', 'Mitglied erstellt.');
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $this->validateMember($request, $member->id);
        $data      = $this->prepareData($validated);
        $data      = $this->handleImageUpload($request, $data, $member);

        $member->update($data);

        return redirect()->route('members.index')->with('success', 'Mitglied aktualisiert.');
    }

    public function updatePhoto(Request $request, Member $member): RedirectResponse
    {
        $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:3072'],
        ]);

        if ($member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }

        $path = $request->file('profile_image')->store('members', 'public');

        $member->update(['profile_image' => $path]);

        return redirect()->route('members.index')
            ->with('success', 'Foto für „' . $member->last_name . ', ' . $member->first_name . '" gespeichert.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        if ($member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }
        $member->delete();

        return redirect()->route('members.index')->with('success', 'Mitglied gelöscht.');
    }

    // ── Private Helpers ─────────────────────────────────────────────────────

    private function validateMember(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name'            => ['required', 'string', 'max:100'],
            'last_name'             => ['required', 'string', 'max:100'],
            'gender'                => ['nullable', 'in:male,female,diverse'],
            'date_of_birth'         => ['nullable', 'date', 'before:today'],
            'status'                => ['required', 'in:active,inactive'],
            'eligible_to_play_date' => ['nullable', 'date'],  // Datum statt Boolean
        ]);
    }

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
