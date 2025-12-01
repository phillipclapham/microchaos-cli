<?php

declare(strict_types=1);

namespace MicroChaos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for MicroChaos_Cache_Analyzer class
 *
 * Tests header collection, aggregation, and report generation.
 * Skips report_summary() as it requires WP_CLI output.
 */
class CacheAnalyzerTest extends TestCase
{
    private \MicroChaos_Cache_Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new \MicroChaos_Cache_Analyzer();
    }

    // =========================================================================
    // Initial State Tests
    // =========================================================================

    #[Test]
    public function new_analyzer_has_empty_cache_headers(): void
    {
        $headers = $this->analyzer->get_cache_headers();

        $this->assertIsArray($headers);
        $this->assertEmpty($headers);
    }

    // =========================================================================
    // collect_headers() Tests
    // =========================================================================

    #[Test]
    public function collect_headers_captures_pressable_edge_cache(): void
    {
        $this->analyzer->collect_headers([
            'x-ac' => 'HIT',
        ]);

        $headers = $this->analyzer->get_cache_headers();

        $this->assertArrayHasKey('x-ac', $headers);
        $this->assertEquals(['HIT' => 1], $headers['x-ac']);
    }

    #[Test]
    public function collect_headers_captures_pressable_batcache(): void
    {
        $this->analyzer->collect_headers([
            'x-nananana' => 'Batcache',
        ]);

        $headers = $this->analyzer->get_cache_headers();

        $this->assertArrayHasKey('x-nananana', $headers);
        $this->assertEquals(['Batcache' => 1], $headers['x-nananana']);
    }

    #[Test]
    public function collect_headers_captures_standard_cache_headers(): void
    {
        $this->analyzer->collect_headers([
            'x-cache' => 'HIT',
            'age' => '120',
            'x-cache-hits' => '5',
        ]);

        $headers = $this->analyzer->get_cache_headers();

        $this->assertArrayHasKey('x-cache', $headers);
        $this->assertArrayHasKey('age', $headers);
        $this->assertArrayHasKey('x-cache-hits', $headers);
    }

    #[Test]
    public function collect_headers_ignores_unknown_headers(): void
    {
        $this->analyzer->collect_headers([
            'x-ac' => 'HIT',
            'content-type' => 'text/html',      // Not a cache header
            'x-custom-header' => 'value',       // Not tracked
            'authorization' => 'Bearer token',  // Not a cache header
        ]);

        $headers = $this->analyzer->get_cache_headers();

        $this->assertCount(1, $headers);
        $this->assertArrayHasKey('x-ac', $headers);
        $this->assertArrayNotHasKey('content-type', $headers);
        $this->assertArrayNotHasKey('x-custom-header', $headers);
    }

    #[Test]
    public function collect_headers_aggregates_multiple_calls(): void
    {
        // Simulate 3 requests with different cache statuses
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);
        $this->analyzer->collect_headers(['x-ac' => 'MISS']);

        $headers = $this->analyzer->get_cache_headers();

        $this->assertEquals(2, $headers['x-ac']['HIT']);
        $this->assertEquals(1, $headers['x-ac']['MISS']);
    }

    #[Test]
    public function collect_headers_handles_empty_array(): void
    {
        $this->analyzer->collect_headers([]);

        $headers = $this->analyzer->get_cache_headers();
        $this->assertEmpty($headers);
    }

    // =========================================================================
    // generate_report() Tests
    // =========================================================================

    #[Test]
    public function generate_report_returns_correct_structure(): void
    {
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);

        $report = $this->analyzer->generate_report(1);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('headers', $report);
        $this->assertArrayHasKey('summary', $report);
    }

    #[Test]
    public function generate_report_calculates_percentages(): void
    {
        // 3 HITs, 1 MISS = 75% HIT, 25% MISS
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);
        $this->analyzer->collect_headers(['x-ac' => 'MISS']);

        $report = $this->analyzer->generate_report(4);

        $breakdown = $report['summary']['x-ac_breakdown'];

        $this->assertEquals(3, $breakdown['HIT']['count']);
        $this->assertEquals(75.0, $breakdown['HIT']['percentage']);
        $this->assertEquals(1, $breakdown['MISS']['count']);
        $this->assertEquals(25.0, $breakdown['MISS']['percentage']);
    }

    #[Test]
    public function generate_report_calculates_average_cache_age(): void
    {
        // Ages: 60, 120, 180 seconds - average should be 120
        $this->analyzer->collect_headers(['age' => '60']);
        $this->analyzer->collect_headers(['age' => '120']);
        $this->analyzer->collect_headers(['age' => '180']);

        $report = $this->analyzer->generate_report(3);

        $this->assertArrayHasKey('average_cache_age', $report['summary']);
        $this->assertEquals(120.0, $report['summary']['average_cache_age']);
    }

    #[Test]
    public function generate_report_handles_repeated_age_values(): void
    {
        // Age 100 appears 3 times, age 200 appears 1 time
        // Average = (100*3 + 200*1) / 4 = 500/4 = 125
        $this->analyzer->collect_headers(['age' => '100']);
        $this->analyzer->collect_headers(['age' => '100']);
        $this->analyzer->collect_headers(['age' => '100']);
        $this->analyzer->collect_headers(['age' => '200']);

        $report = $this->analyzer->generate_report(4);

        $this->assertEquals(125.0, $report['summary']['average_cache_age']);
    }

    #[Test]
    public function generate_report_without_age_has_no_average(): void
    {
        $this->analyzer->collect_headers(['x-ac' => 'HIT']);

        $report = $this->analyzer->generate_report(1);

        $this->assertArrayNotHasKey('average_cache_age', $report['summary']);
    }

    #[Test]
    public function generate_report_with_empty_headers(): void
    {
        $report = $this->analyzer->generate_report(0);

        $this->assertIsArray($report);
        $this->assertEmpty($report['headers']);
        $this->assertEmpty($report['summary']);
    }
}
