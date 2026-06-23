<?php

namespace Modules\Members\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Members\Models\Member;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Member::query();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->q . '%')
                  ->orWhere('last_name',  'like', '%' . $request->q . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $members = $query->orderBy('last_name')->paginate(25)->withQueryString();

        // JS-Daten: foreach, keine Closures
        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = [
                'id'               => $m->id,
                'first_name'       => $m->first_name,
                'last_name'        => $m->last_name,
                'gender'           => $m->gender,
                'date_of_birth'    => $m->date_of_birth?->format('Y-m-d'),
                'status'           => $m->status,
                'eligible_to_play' => (bool) $m->eligible_to_play,
                'profile_image'    => $m->profile_image
                    ? asset('storage/' . $m->profile_image)
                    : null,
            ];
        }

        return view('members::index', compact('members', 'membersJs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMember($request);
        $data      = $this->prepareData($validated);
        $data      = $this->handleImageUpload($request, $data);

        Member::create($data);

        return redirect()->route('members.index')->with('success', 'Mitglied angelegt.');
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $this->validateMember($request, $member->id);
        $data      = $this->prepareData($validated);
        $data      = $this->handleImageUpload($request, $data, $member);

        $member->update($data);

        return redirect()->route('members.index')->with('success', 'Mitglied aktualisiert.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        // Profilbild vom Server löschen
        if ($member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }

        $member->delete();

        return redirect()->route('members.index')->with('success', 'Mitglied gelöscht.');
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private function validateMember(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'gender'           => ['nullable', 'in:male,female,diverse'],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],
            'status'           => ['required', 'in:active,inactive'],
            'eligible_to_play' => ['nullable', 'boolean'],
            'profile_image'    => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:3072'],
        ]);
    }

    private function prepareData(array $validated): array
    {
        return [
            'first_name'       => $validated['first_name'],
            'last_name'        => $validated['last_name'],
            'gender'           => $validated['gender'] ?? null,
            'date_of_birth'    => $validated['date_of_birth'] ?? null,
            'status'           => $validated['status'],
            'eligible_to_play' => (bool) ($validated['eligible_to_play'] ?? false),
        ];
    }

    private function handleImageUpload(Request $request, array $data, ?Member $member = null): array
    {
        if (!$request->hasFile('profile_image')) {
            return $data;
        }

        // Altes Bild löschen
        if ($member && $member->profile_image) {
            Storage::disk('public')->delete($member->profile_image);
        }

        $data['profile_image'] = $request->file('profile_image')
            ->store('members', 'public');

        return $data;
    }
}