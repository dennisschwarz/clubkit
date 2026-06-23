<?php

namespace Modules\Members\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Members\Models\Member;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Member::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name',  'like', "%$search%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $members = $query->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        // JS-Daten sauber im Controller aufbereiten
        $membersJs = [];
        foreach ($members as $m) {
            $membersJs[$m->id] = [
                'id'               => $m->id,
                'first_name'       => $m->first_name,
                'last_name'        => $m->last_name,
                'gender'           => $m->gender ?? '',
                'date_of_birth'    => $m->date_of_birth?->format('Y-m-d') ?? '',
                'status'           => $m->status,
                'eligible_to_play' => (bool) $m->eligible_to_play,
            ];
        }

        return view('members::index', compact('members', 'membersJs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'gender'           => ['nullable', 'in:male,female,diverse'],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],
            'eligible_to_play' => ['boolean'],
        ]);

        $validated['eligible_to_play'] = $request->boolean('eligible_to_play');
        $validated['status']           = 'active';

        Member::create($validated);

        return redirect()->route('members.index')->with('success', 'Mitglied angelegt.');
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'gender'           => ['nullable', 'in:male,female,diverse'],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],
            'eligible_to_play' => ['boolean'],
            'status'           => ['required', 'in:active,inactive'],
        ]);

        $validated['eligible_to_play'] = $request->boolean('eligible_to_play');
        $member->update($validated);

        return redirect()->route('members.index')->with('success', 'Mitglied aktualisiert.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->delete();

        return redirect()->route('members.index')->with('success', 'Mitglied gelöscht.');
    }
}
