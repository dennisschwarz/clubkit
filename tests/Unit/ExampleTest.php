<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Minimaler Unit-Test – prüft dass PHP und PHPUnit funktionieren.
 * Kein Laravel-Bootstrap, kein HTTP, kein DB.
 */
class ExampleTest extends TestCase
{
    public function test_php_is_running(): void
    {
        $this->assertTrue(true);
    }
}
