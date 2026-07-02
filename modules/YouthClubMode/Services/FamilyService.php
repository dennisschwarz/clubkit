<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Services;

use Illuminate\Database\Eloquent\Collection;

/**
 * Computes family data for members from a MemberRelation collection snapshot.
 *
 * This service has no database access – it transforms an already-loaded
 * collection. This keeps it fully testable without a database connection.
 *
 * Database storage convention:
 *   primary_member_id   = parent (or canonical left-side sibling)
 *   secondary_member_id = child  (or canonical right-side sibling)
 *   relationship        = 'father' | 'mother' | 'sibling'
 */
class FamilyService
{
    /**
     * Builds the family data structure for a single member.
     *
     * @param  int        $memberId
     * @param  Collection $allRelations  Complete MemberRelation collection (unpaginated)
     * @param  array<int, array{id: int, name: string, gender: string|null, date_of_birth: string|null}>  $allMembersJs
     * @return array{father: array|null, mother: array|null, children: array, siblings: array}
     */
    public function buildFamilyData(int $memberId, Collection $allRelations, array $allMembersJs): array
    {
        $family = $this->emptyFamily();

        foreach ($allRelations as $r) {
            $pid = $r->primary_member_id;
            $sid = $r->secondary_member_id;
            $rel = $r->relationship;

            // This member is the CHILD – the primary member is the parent
            if ($sid === $memberId && $rel === 'father') {
                $family['father'] = [
                    'relation_id' => $r->id,
                    'id'          => $pid,
                    'name'        => $allMembersJs[$pid]['name'] ?? '?',
                ];
            }

            if ($sid === $memberId && $rel === 'mother') {
                $family['mother'] = [
                    'relation_id' => $r->id,
                    'id'          => $pid,
                    'name'        => $allMembersJs[$pid]['name'] ?? '?',
                ];
            }

            // This member is the PARENT – secondary is the child
            if ($pid === $memberId && in_array($rel, ['father', 'mother'], true)) {
                $family['children'][] = [
                    'relation_id'     => $r->id,
                    'id'              => $sid,
                    'name'            => $allMembersJs[$sid]['name'] ?? '?',
                    'parent_relation' => $rel, // what this member is to the child
                ];
            }

            // Siblings (canonical form: lower ID = primary; check both directions)
            if ($rel === 'sibling' && ($pid === $memberId || $sid === $memberId)) {
                $otherId = $pid === $memberId ? $sid : $pid;
                $family['siblings'][] = [
                    'relation_id' => $r->id,
                    'id'          => $otherId,
                    'name'        => $allMembersJs[$otherId]['name'] ?? '?',
                ];
            }
        }

        return $family;
    }

    /**
     * Returns the empty family structure used when no relations exist.
     *
     * @return array{father: null, mother: null, children: array, siblings: array}
     */
    public function emptyFamily(): array
    {
        return [
            'father'   => null,
            'mother'   => null,
            'children' => [],
            'siblings' => [],
        ];
    }
}
