<?php

declare(strict_types=1);

namespace Modules\Import;

/**
 * Source-neutral Data Transfer Object for a member to be imported.
 * No source-specific fields – all importers produce this DTO.
 * Consumed by MemberImportService to write records to the database.
 *
 * eligible_to_play_date: YYYY-MM-DD when playing eligibility exists, null otherwise.
 *   The derived eligibility boolean is computed by the Member model accessor:
 *   eligible_to_play = (eligible_to_play_date !== null && !eligible_to_play_date->isFuture())
 */
final class MemberData
{
    public function __construct(
        public readonly string  $first_name,
        public readonly string  $last_name,
        public readonly ?string $date_of_birth,          // YYYY-MM-DD or null
        public readonly ?string $gender,                  // 'male' | 'female' | 'diverse' | null
        public readonly ?string $pass_number,             // e.g. '0765-0056' or null
        public readonly ?string $eligible_to_play_date = null, // YYYY-MM-DD or null
        public readonly string  $status                = 'active',
        public readonly array   $custom_fields         = [], // ['cf_slug' => 'value']
    ) {}
}
