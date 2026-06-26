<?php

declare(strict_types=1);

namespace Modules\Import;

/**
 * Quellneutrales Data Transfer Object für einen zu importierenden Member.
 * Kein einziges source-spezifisches Feld – alle Quellen liefern dieses DTO.
 * Wird vom MemberImportService in die DB geschrieben.
 *
 * eligible_to_play_date: YYYY-MM-DD wenn Spielrecht vorhanden, null wenn nicht.
 *   Abgeleitete Spielberechtigung (bool) berechnet das Member-Model per Accessor:
 *   eligible_to_play = (eligible_to_play_date !== null && !eligible_to_play_date->isFuture())
 */
final class MemberData
{
    public function __construct(
        public readonly string  $first_name,
        public readonly string  $last_name,
        public readonly ?string $date_of_birth,          // YYYY-MM-DD oder null
        public readonly ?string $gender,                  // 'male' | 'female' | 'diverse' | null
        public readonly ?string $pass_number,             // z.B. '0765-0056' oder null
        public readonly ?string $eligible_to_play_date = null, // YYYY-MM-DD oder null
        public readonly string  $status                = 'active',
        public readonly array   $custom_fields         = [], // ['cf_slug' => 'Wert']
    ) {}
}
