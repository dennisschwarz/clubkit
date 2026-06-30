<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Http\Requests\StoreRelationRequest;
use Modules\YouthClubMode\Models\MemberRelation;

/**
 * Manages family relationships between members (JSON-only, AJAX from youth-club-mode.js).
 *
 * Routes:
 *   POST   /members/{member}/relations               → store()
 *   DELETE /members/{member}/relations/{relation}    → destroy()
 *
 * UI relationship types and their canonical DB representation:
 *   'father'    → selected member IS the father of the current member
 *   'mother'    → selected member IS the mother of the current member
 *   'father_of' → current member IS the father of the selected member
 *   'mother_of' → current member IS the mother of the selected member
 *   'sibling'   → siblings (stored in canonical form: lower ID = primary_member_id)
 *
 * DB record always stores:
 *   primary_member_id   = parent (or lower-ID sibling)
 *   secondary_member_id = child  (or higher-ID sibling)
 *   relationship        = 'father' | 'mother' | 'sibling'
 *
 * Activity logging: create/delete events are logged here manually via activity()
 * because MemberRelation is a thin join record without LogsActivity trait.
 */
class FamilyController extends Controller
{
    /**
     * Creates a new family relation between two members.
     *
     * Validation is delegated to StoreRelationRequest.
     *
     * Enforces:
     *   - A member cannot be related to themselves
     *   - Each child can have at most one father and one mother
     *   - Sibling relations are not stored as duplicates
     *
     * @param  StoreRelationRequest $request
     * @param  Member               $member
     * @return JsonResponse
     */
    public function store(StoreRelationRequest $request, Member $member): JsonResponse
    {
        $relatedId    = (int) $request->validated()['related_member_id'];
        $relationship = $request->validated()['relationship'];

        // Prevent self-reference
        if ($relatedId === $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ein Mitglied kann nicht mit sich selbst verknüpft werden.',
            ], 422);
        }

        // Normalise primary/secondary direction from the UI relationship type
        [$primaryId, $secondaryId, $storedRel] = $this->resolveDirection(
            $member->id,
            $relatedId,
            $relationship
        );

        // Father/mother: max one per child
        if (in_array($storedRel, ['father', 'mother'], true)) {
            $exists = MemberRelation::where('secondary_member_id', $secondaryId)
                                    ->where('relationship', $storedRel)
                                    ->exists();
            if ($exists) {
                $label = $storedRel === 'father' ? 'Vater' : 'Mutter';
                return response()->json([
                    'success' => false,
                    'message' => "Für dieses Mitglied ist bereits ein {$label} eingetragen.",
                ], 422);
            }
        }

        // Sibling: prevent duplicates (canonical order already applied)
        if ($storedRel === 'sibling') {
            $exists = MemberRelation::where('primary_member_id',   $primaryId)
                                    ->where('secondary_member_id', $secondaryId)
                                    ->where('relationship', 'sibling')
                                    ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Diese Geschwister-Verbindung existiert bereits.',
                ], 422);
            }
        }

        $relation = MemberRelation::create([
            'primary_member_id'   => $primaryId,
            'secondary_member_id' => $secondaryId,
            'relationship'        => $storedRel,
            'created_by'          => $request->user()->id,
        ]);

        // Manual activity log: MemberRelation has no LogsActivity trait
        activity('youth-club-mode')
            ->performedOn($member)
            ->withProperties([
                'relation_id'         => $relation->id,
                'primary_member_id'   => $primaryId,
                'secondary_member_id' => $secondaryId,
                'relationship'        => $storedRel,
            ])
            ->event('relation_created')
            ->log('relation_created');

        return response()->json([
            'success'  => true,
            'message'  => 'Familiäre Verbindung eingetragen.',
            'relation' => [
                'id'                  => $relation->id,
                'primary_member_id'   => $relation->primary_member_id,
                'secondary_member_id' => $relation->secondary_member_id,
                'relationship'        => $relation->relationship,
            ],
        ]);
    }

    /**
     * Deletes a family relation.
     *
     * The requesting member must be part of the relation (ownership check).
     *
     * @param  Member         $member
     * @param  MemberRelation $relation
     * @return JsonResponse
     */
    public function destroy(Member $member, MemberRelation $relation): JsonResponse
    {
        // Ownership check: the member must be part of this relation
        if ($relation->primary_member_id   !== $member->id
         && $relation->secondary_member_id !== $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert.',
            ], 403);
        }

        $relationId = $relation->id;

        // Capture details before deletion for the activity log
        $logProperties = [
            'relation_id'         => $relationId,
            'primary_member_id'   => $relation->primary_member_id,
            'secondary_member_id' => $relation->secondary_member_id,
            'relationship'        => $relation->relationship,
        ];

        $relation->delete();

        // Manual activity log: MemberRelation has no LogsActivity trait
        activity('youth-club-mode')
            ->performedOn($member)
            ->withProperties($logProperties)
            ->event('relation_deleted')
            ->log('relation_deleted');

        return response()->json([
            'success'     => true,
            'message'     => 'Verbindung entfernt.',
            'relation_id' => $relationId,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolves the canonical primary/secondary/relationship triple from the UI relationship type.
     *
     * @param  int    $currentId       The member whose modal triggered the request.
     * @param  int    $relatedId       The selected related member.
     * @param  string $uiRelationship  One of: father, mother, father_of, mother_of, sibling.
     * @return array{int, int, string} [$primaryId, $secondaryId, $storedRel]
     */
    private function resolveDirection(int $currentId, int $relatedId, string $uiRelationship): array
    {
        return match ($uiRelationship) {
            // Selected member IS the father of the current member
            'father'    => [$relatedId, $currentId, 'father'],
            // Selected member IS the mother of the current member
            'mother'    => [$relatedId, $currentId, 'mother'],
            // Current member IS the father of the selected member
            'father_of' => [$currentId, $relatedId, 'father'],
            // Current member IS the mother of the selected member
            'mother_of' => [$currentId, $relatedId, 'mother'],
            // Siblings: canonical form – lower ID always first
            'sibling'   => [min($currentId, $relatedId), max($currentId, $relatedId), 'sibling'],
            // Fallback (should never be reached due to StoreRelationRequest validation)
            default     => [$relatedId, $currentId, $uiRelationship],
        };
    }
}
