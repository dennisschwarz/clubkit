<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Services;

use Illuminate\Database\Eloquent\Collection;

/**
 * Berechnet Familiendaten für Mitglieder aus dem MemberRelation-Set.
 *
 * Die Klasse hat keinen DB-Zugriff – sie transformiert nur einen bereits
 * geladenen Collection-Snapshot. So kann sie in Tests ohne DB verwendet werden.
 *
 * Beziehungstypen im DB-Schema:
 *   primary_member_id   = Elternteil (oder linkes Geschwisterkind)
 *   secondary_member_id = Kind (oder rechtes Geschwisterkind)
 *   relationship        = 'father' | 'mother' | 'sibling'
 */
class FamilyService
{
    /**
     * Familiendaten für ein einzelnes Mitglied berechnen.
     *
     * @param  int        $memberId
     * @param  Collection $allRelations   Vollständige MemberRelation-Collection (ungepaginiert)
     * @param  array      $allMembersJs   [id => ['id', 'name', 'gender', 'date_of_birth']]
     * @return array{father: ?array, mother: ?array, children: array, siblings: array}
     */
    public function buildFamilyData(int $memberId, Collection $allRelations, array $allMembersJs): array
    {
        $family = $this->emptyFamily();

        foreach ($allRelations as $r) {
            $pid = $r->primary_member_id;
            $sid = $r->secondary_member_id;
            $rel = $r->relationship;

            // Dieses Mitglied ist das KIND – das primary-Mitglied ist der Elternteil
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

            // Dieses Mitglied ist ELTERNTEIL – secondary ist das Kind
            if ($pid === $memberId && in_array($rel, ['father', 'mother'], true)) {
                $family['children'][] = [
                    'relation_id'     => $r->id,
                    'id'              => $sid,
                    'name'            => $allMembersJs[$sid]['name'] ?? '?',
                    'parent_relation' => $rel, // was dieses Mitglied für das Kind ist
                ];
            }

            // Geschwister (kanonische Form: niedrigere ID = primary; beide Richtungen prüfen)
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
     * Leeres Familien-Array als Grundstruktur.
     * Wird verwendet wenn noch keine Verbindungen existieren.
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
