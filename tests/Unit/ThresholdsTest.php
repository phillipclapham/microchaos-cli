<?php

declare(strict_types=1);

namespace MicroChaos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for MicroChaos_Thresholds class
 *
 * Tests pure PHP functionality without WordPress dependencies.
 * WP-dependent methods (save_thresholds, load_thresholds) are skipped.
 */
class ThresholdsTest extends TestCase
{
    // =========================================================================
    // get_thresholds() Tests
    // =========================================================================

    #[Test]
    public function get_thresholds_returns_response_time_defaults(): void
    {
        $thresholds = \MicroChaos_Thresholds::get_thresholds('response_time');

        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('good', $thresholds);
        $this->assertArrayHasKey('warn', $thresholds);
        $this->assertArrayHasKey('critical', $thresholds);

        // Verify actual default values
        $this->assertEquals(1.0, $thresholds['good']);
        $this->assertEquals(2.0, $thresholds['warn']);
        $this->assertEquals(3.0, $thresholds['critical']);
    }

    #[Test]
    public function get_thresholds_returns_memory_usage_defaults(): void
    {
        $thresholds = \MicroChaos_Thresholds::get_thresholds('memory_usage');

        $this->assertIsArray($thresholds);
        $this->assertEquals(50, $thresholds['good']);
        $this->assertEquals(70, $thresholds['warn']);
        $this->assertEquals(85, $thresholds['critical']);
    }

    #[Test]
    public function get_thresholds_returns_error_rate_defaults(): void
    {
        $thresholds = \MicroChaos_Thresholds::get_thresholds('error_rate');

        $this->assertIsArray($thresholds);
        $this->assertEquals(1, $thresholds['good']);
        $this->assertEquals(5, $thresholds['warn']);
        $this->assertEquals(10, $thresholds['critical']);
    }

    #[Test]
    public function get_thresholds_returns_zeros_for_unknown_type(): void
    {
        $thresholds = \MicroChaos_Thresholds::get_thresholds('unknown_type');

        $this->assertIsArray($thresholds);
        $this->assertEquals(0, $thresholds['good']);
        $this->assertEquals(0, $thresholds['warn']);
        $this->assertEquals(0, $thresholds['critical']);
    }

    // =========================================================================
    // get_php_memory_limit_mb() Tests
    // =========================================================================

    #[Test]
    public function get_php_memory_limit_mb_returns_positive_float(): void
    {
        $limit = \MicroChaos_Thresholds::get_php_memory_limit_mb();

        $this->assertIsFloat($limit);
        $this->assertGreaterThan(0, $limit);
    }

    // =========================================================================
    // generate_chart() Tests
    // =========================================================================

    #[Test]
    public function generate_chart_produces_ascii_output(): void
    {
        $values = [
            'Fast' => 10,
            'Medium' => 25,
            'Slow' => 5,
        ];

        $chart = \MicroChaos_Thresholds::generate_chart($values, 'Response Times');

        $this->assertIsString($chart);
        $this->assertStringContainsString('Response Times', $chart);
        $this->assertStringContainsString('Fast', $chart);
        $this->assertStringContainsString('Medium', $chart);
        $this->assertStringContainsString('Slow', $chart);
        $this->assertStringContainsString('█', $chart); // Contains bar characters
    }

    #[Test]
    public function generate_chart_handles_all_zero_values(): void
    {
        $values = [
            'A' => 0,
            'B' => 0,
        ];

        // Should not throw division by zero
        $chart = \MicroChaos_Thresholds::generate_chart($values, 'Zero Chart');

        $this->assertIsString($chart);
        $this->assertStringContainsString('Zero Chart', $chart);
    }

    // =========================================================================
    // generate_histogram() Tests
    // =========================================================================

    #[Test]
    public function generate_histogram_produces_distribution_output(): void
    {
        $times = [0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 0.8, 1.2, 1.8, 2.2];

        $histogram = \MicroChaos_Thresholds::generate_histogram($times);

        $this->assertIsString($histogram);
        $this->assertStringContainsString('Response Time Distribution', $histogram);
        $this->assertStringContainsString('█', $histogram);
    }

    #[Test]
    public function generate_histogram_returns_empty_for_empty_array(): void
    {
        $histogram = \MicroChaos_Thresholds::generate_histogram([]);

        $this->assertEquals('', $histogram);
    }

    #[Test]
    public function generate_histogram_handles_identical_values(): void
    {
        // All same value - tests division by zero edge case
        $times = [1.5, 1.5, 1.5, 1.5, 1.5];

        $histogram = \MicroChaos_Thresholds::generate_histogram($times);

        $this->assertIsString($histogram);
        // Should not throw, should produce valid output
    }

    // =========================================================================
    // format_value() Tests
    // =========================================================================

    #[Test]
    public function format_value_applies_green_for_good_response_time(): void
    {
        // 0.5s is under 1.0s threshold (good)
        $formatted = \MicroChaos_Thresholds::format_value(0.5, 'response_time');

        $this->assertStringContainsString("\033[32m", $formatted); // Green ANSI code
        $this->assertStringContainsString('0.5s', $formatted);
    }

    #[Test]
    public function format_value_applies_yellow_for_warn_response_time(): void
    {
        // 1.5s is between 1.0s and 2.0s (warn)
        $formatted = \MicroChaos_Thresholds::format_value(1.5, 'response_time');

        $this->assertStringContainsString("\033[33m", $formatted); // Yellow ANSI code
        $this->assertStringContainsString('1.5s', $formatted);
    }

    #[Test]
    public function format_value_applies_red_for_critical_response_time(): void
    {
        // 5.0s is over 2.0s threshold (critical)
        $formatted = \MicroChaos_Thresholds::format_value(5.0, 'response_time');

        $this->assertStringContainsString("\033[31m", $formatted); // Red ANSI code
        $this->assertStringContainsString('5s', $formatted);
    }

    #[Test]
    public function format_value_returns_raw_value_for_unknown_type(): void
    {
        $formatted = \MicroChaos_Thresholds::format_value(42.5, 'unknown_type');

        // Should return just the number, no ANSI codes
        $this->assertEquals('42.5', $formatted);
    }

    // =========================================================================
    // calibrate_thresholds() Tests (non-persistent)
    // =========================================================================

    #[Test]
    public function calibrate_thresholds_calculates_from_test_results(): void
    {
        $testResults = [
            'timing' => ['avg' => 0.5],
            'error_rate' => 2.0,
            'memory' => ['avg' => 64], // 64 MB
        ];

        // Don't persist (avoids WordPress dependency)
        $thresholds = \MicroChaos_Thresholds::calibrate_thresholds(
            $testResults,
            'test_profile',
            false // persist = false
        );

        $this->assertIsArray($thresholds);

        // Response time thresholds should be based on 0.5s avg
        $this->assertArrayHasKey('response_time', $thresholds);
        $this->assertEquals(0.5, $thresholds['response_time']['good']);   // 0.5 * 1.0
        $this->assertEquals(0.75, $thresholds['response_time']['warn']);  // 0.5 * 1.5
        $this->assertEquals(1.0, $thresholds['response_time']['critical']); // 0.5 * 2.0

        // Error rate thresholds should be based on 2.0%
        $this->assertArrayHasKey('error_rate', $thresholds);
        $this->assertEquals(2.0, $thresholds['error_rate']['good']);  // 2.0 * 1.0
        $this->assertEquals(3.0, $thresholds['error_rate']['warn']);  // 2.0 * 1.5
        $this->assertEquals(4.0, $thresholds['error_rate']['critical']); // 2.0 * 2.0
    }
}
