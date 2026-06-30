<?php

declare(strict_types=1);

/**
 * Minimal unit test – verifies PHP and the test runner are working correctly.
 * No Laravel bootstrap, no HTTP, no DB.
 */
test('php is running', function () {
    expect(true)->toBeTrue();
});
