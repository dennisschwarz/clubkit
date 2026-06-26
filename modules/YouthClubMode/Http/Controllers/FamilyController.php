<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberRelation;

/**
 * Familiäre Verbindungen zwischen Mitgliedern verwalten.
 *
 * Alle Endpunkte sind JSON-only (AJAX aus youth-club-mode.js).
 *
 * POST   /members/{member}/relations          → store()
 * DELETE /members/{member}/relations/{relation} → destroy()
 *
 * Beziehungstypen aus dem UI:
 *   'father'    → Das ausgewählte Mitglied IST der Vater des aktuellen Mitglieds
 *   'mother'    → Das ausgewählte Mitglied IST die Mutter des aktuellen Mitglieds
 *   'father_of' → Das aktuelle Mitglied IST der Vater des ausgewählten Mitglieds
 *   'mother_of' → Das aktuelle Mitglied IST die Mutter des ausgewählten Mitglieds
 *   'sibling'   → Geschwister (unidirektional gespeichert, kanonische Form)
 *
 * Gespeicherte DB-Einträge immer:
 *   primary_member_id   = Elternteil
 *   secondary_member_id = Kind
 *   relationship        = 'father' | 'mother' | 'sibling'
 */
class FamilyController extends Controller
{
    /**
     * Neue familiäre Verbindung anlegen.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'relationship'      => ['required', 'string', 'in:father,mother,father_of,mother_of,sibling'],
            'related_member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $relatedId    = (int) $validated['related_member_id'];
        $relationship = $validated['relationship'];

        // Selbstreferenz verhindern
        if ($relatedId === $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ein Mitglied kann nicht mit sich selbst verknüpft werden.',
            ], 422);
        }

        // Primär/Sekundär je nach Beziehungsrichtung bestimmen
        [$primaryId, $secondaryId, $storedRel] = $this->resolveDirection(
            $member->id,
            $relatedId,
            $relationship
        );

        // Für Vater/Mutter: max. 1 pro Kind
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

        // Geschwister: Duplikat prüfen (kanonische Reihenfolge bereits gesetzt)
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
            'created_by'          => auth()->id(),
        ]);

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
     * Familiäre Verbindung entfernen.
     */
    public function destroy(Member $member, MemberRelation $relation): JsonResponse
    {
        // Sicherheitscheck: das Mitglied muss Teil dieser Verbindung sein
        if ($relation->primary_member_id   !== $member->id
         && $relation->secondary_member_id !== $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Zugriff verweigert.',
            ], 403);
        }

        $relationId = $relation->id;
        $relation->delete();

        return response()->json([
            'success'     => true,
            'message'     => 'Verbindung entfernt.',
            'relation_id' => $relationId,
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────

    /**
     * Bestimmt primary/secondary/relationship aus dem UI-Beziehungstyp.
     *
     * @return array{int, int, string} [$primaryId, $secondaryId, $storedRel]
     */
    private function resolveDirection(int $currentId, int $relatedId, string $uiRelationship): array
    {
        return match ($uiRelationship) {
            // Ausgewähltes Mitglied IST der Vater des aktuellen
            'father'    => [$relatedId, $currentId, 'father'],
            // Ausgewähltes Mitglied IST die Mutter des aktuellen
            'mother'    => [$relatedId, $currentId, 'mother'],
            // Aktuelles Mitglied IST der Vater des ausgewählten
            'father_of' => [$currentId, $relatedId, 'father'],
            // Aktuelles Mitglied IST die Mutter des ausgewählten
            'mother_of' => [$currentId, $relatedId, 'mother'],
            // Geschwister: kanonische Form – niedrigere ID immer zuerst
            'sibling'   => [min($currentId, $relatedId), max($currentId, $relatedId), 'sibling'],
            // Fallback (sollte nie erreicht werden dank Validation)
            default     => [$relatedId, $currentId, $uiRelationship],
        };
    }
}
