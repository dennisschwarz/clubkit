<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberParent;

/**
 * Saves or removes guardian links for a member.
 * PATCH /members/{member}/parents
 */
class ParentLinkController extends Controller
{
    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'father_id' => ['nullable', 'integer', 'exists:members,id'],
            'mother_id' => ['nullable', 'integer', 'exists:members,id'],
        ]);

        foreach (['father_id' => 'father', 'mother_id' => 'mother'] as $field => $relationship) {
            $parentId = $validated[$field] ?? null;

            if ($parentId && (int) $parentId === $member->id) {
                return back()->with('error', 'A member cannot be their own guardian.');
            }

            if ($parentId) {
                MemberParent::updateOrCreate(
                    ['member_id' => $member->id, 'relationship' => $relationship],
                    ['parent_member_id' => $parentId]
                );
            } else {
                MemberParent::where('member_id', $member->id)
                    ->where('relationship', $relationship)
                    ->delete();
            }
        }

        return back()->with('success', 'Guardian links for "' . $member->last_name . ', ' . $member->first_name . '" saved.');
    }
}
