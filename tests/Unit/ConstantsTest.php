<?php

declare(strict_types=1);

namespace MicroChaos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for MicroChaos_Constants class
 *
 * Verifies constant definitions are correct and consistent.
 */
class ConstantsTest extends TestCase
{
    // =========================================================================
    // Baseline Storage Constants
    // =========================================================================

    #[Test]
    public function baseline_ttl_equals_30_days_in_seconds(): void
    {
        // 30 days = 30 * 24 * 60 * 60 = 2,592,000 seconds
        $this->assertEquals(2592000, \MicroChaos_Constants::BASELINE_TTL);
        $this->assertEquals(
            30 * 24 * 60 * 60,
            \MicroChaos_Constants::BASELINE_TTL,
            'BASELINE_TTL should equal 30 days in seconds'
        );
    }

    // =========================================================================
    // Time Conversion Constants
    // =========================================================================

    #[Test]
    public function time_constants_are_mathematically_correct(): void
    {
        $this->assertEquals(60, \MicroChaos_Constants::SECONDS_PER_MINUTE);
        $this->assertEquals(3600, \MicroChaos_Constants::SECONDS_PER_HOUR);
        $this->assertEquals(86400, \MicroChaos_Constants::SECONDS_PER_DAY);

        // Verify relationships
        $this->assertEquals(
            \MicroChaos_Constants::SECONDS_PER_MINUTE * 60,
            \MicroChaos_Constants::SECONDS_PER_HOUR,
            'SECONDS_PER_HOUR should equal 60 minutes'
        );
        $this->assertEquals(
            \MicroChaos_Constants::SECONDS_PER_HOUR * 24,
            \MicroChaos_Constants::SECONDS_PER_DAY,
            'SECONDS_PER_DAY should equal 24 hours'
        );
    }

    // =========================================================================
    // HTTP Status Code Constants
    // =========================================================================

    #[Test]
    public function http_status_codes_are_standard_values(): void
    {
        $this->assertEquals(200, \MicroChaos_Constants::HTTP_OK);
        $this->assertEquals(404, \MicroChaos_Constants::HTTP_NOT_FOUND);
        $this->assertEquals(500, \MicroChaos_Constants::HTTP_SERVER_ERROR);
    }

    // =========================================================================
    // Parallel Execution Constants
    // =========================================================================

    #[Test]
    public function parallel_execution_defaults_are_reasonable(): void
    {
        // 10 minutes timeout
        $this->assertEquals(600, \MicroChaos_Constants::DEFAULT_PARALLEL_TIMEOUT);
        $this->assertEquals(
            10 * 60,
            \MicroChaos_Constants::DEFAULT_PARALLEL_TIMEOUT,
            'DEFAULT_PARALLEL_TIMEOUT should equal 10 minutes'
        );

        // 3 workers default
        $this->assertEquals(3, \MicroChaos_Constants::DEFAULT_WORKERS);
        $this->assertGreaterThan(0, \MicroChaos_Constants::DEFAULT_WORKERS);
    }
}
