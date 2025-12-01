<?php

declare(strict_types=1);

namespace MicroChaos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spy Logger for testing
 *
 * Implements MicroChaos_Logger_Interface and records all method calls
 * for verification. This demonstrates the INTERFACE MOCKING PATTERN:
 * we can inject any logger implementation and verify delegation works.
 */
class SpyLogger implements \MicroChaos_Logger_Interface
{
    /** @var array<array{method: string, message: string}> */
    public array $calls = [];

    public function log(string $message): void
    {
        $this->calls[] = ['method' => 'log', 'message' => $message];
    }

    public function success(string $message): void
    {
        $this->calls[] = ['method' => 'success', 'message' => $message];
    }

    public function warning(string $message): void
    {
        $this->calls[] = ['method' => 'warning', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->calls[] = ['method' => 'error', 'message' => $message];
    }

    public function debug(string $message): void
    {
        $this->calls[] = ['method' => 'debug', 'message' => $message];
    }

    /**
     * Get the last call recorded
     */
    public function getLastCall(): ?array
    {
        return $this->calls[count($this->calls) - 1] ?? null;
    }

    /**
     * Check if a specific method was called with a message
     */
    public function wasCalledWith(string $method, string $message): bool
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method && $call['message'] === $message) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Unit tests for MicroChaos_Log facade
 *
 * Tests logger registration, delegation, and the interface mocking pattern.
 * This is the key demonstration that dependency injection works for testing.
 */
class LogFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the logger before each test to ensure clean state
        \MicroChaos_Log::reset();
    }

    protected function tearDown(): void
    {
        // Restore the Null_Logger after tests (as bootstrap.php sets it)
        \MicroChaos_Log::set_logger(new \MicroChaos_Null_Logger());
    }

    // =========================================================================
    // Logger Registration Tests
    // =========================================================================

    #[Test]
    public function has_logger_returns_false_after_reset(): void
    {
        $this->assertFalse(\MicroChaos_Log::has_logger());
    }

    #[Test]
    public function set_logger_makes_has_logger_return_true(): void
    {
        \MicroChaos_Log::set_logger(new \MicroChaos_Null_Logger());

        $this->assertTrue(\MicroChaos_Log::has_logger());
    }

    #[Test]
    public function get_logger_returns_set_logger(): void
    {
        $logger = new SpyLogger();
        \MicroChaos_Log::set_logger($logger);

        $this->assertSame($logger, \MicroChaos_Log::get_logger());
    }

    #[Test]
    public function get_logger_returns_null_when_not_set(): void
    {
        $this->assertNull(\MicroChaos_Log::get_logger());
    }

    #[Test]
    public function reset_clears_the_logger(): void
    {
        \MicroChaos_Log::set_logger(new \MicroChaos_Null_Logger());
        $this->assertTrue(\MicroChaos_Log::has_logger());

        \MicroChaos_Log::reset();

        $this->assertFalse(\MicroChaos_Log::has_logger());
        $this->assertNull(\MicroChaos_Log::get_logger());
    }

    // =========================================================================
    // Delegation Tests (Interface Mocking Pattern Demonstration)
    // =========================================================================

    #[Test]
    public function log_delegates_to_logger(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::log('Test message');

        $this->assertCount(1, $spy->calls);
        $this->assertTrue($spy->wasCalledWith('log', 'Test message'));
    }

    #[Test]
    public function success_delegates_to_logger(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::success('Operation succeeded');

        $this->assertTrue($spy->wasCalledWith('success', 'Operation succeeded'));
    }

    #[Test]
    public function warning_delegates_to_logger(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::warning('Something looks wrong');

        $this->assertTrue($spy->wasCalledWith('warning', 'Something looks wrong'));
    }

    #[Test]
    public function error_delegates_to_logger(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::error('Critical failure');

        $this->assertTrue($spy->wasCalledWith('error', 'Critical failure'));
    }

    #[Test]
    public function debug_delegates_to_logger(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::debug('Debug info');

        $this->assertTrue($spy->wasCalledWith('debug', 'Debug info'));
    }

    // =========================================================================
    // No Logger Behavior Tests
    // =========================================================================

    #[Test]
    public function log_does_nothing_when_no_logger_set(): void
    {
        // No logger set (reset in setUp)
        // Should not throw, should silently do nothing
        \MicroChaos_Log::log('This should not throw');

        // If we get here without exception, test passes
        $this->assertFalse(\MicroChaos_Log::has_logger());
    }

    #[Test]
    public function all_methods_safe_when_no_logger(): void
    {
        // All methods should be safe to call with no logger
        \MicroChaos_Log::log('test');
        \MicroChaos_Log::success('test');
        \MicroChaos_Log::warning('test');
        \MicroChaos_Log::error('test');
        \MicroChaos_Log::debug('test');

        // No exceptions = success
        $this->assertTrue(true);
    }

    // =========================================================================
    // Multiple Calls Test
    // =========================================================================

    #[Test]
    public function multiple_log_calls_are_all_recorded(): void
    {
        $spy = new SpyLogger();
        \MicroChaos_Log::set_logger($spy);

        \MicroChaos_Log::log('First');
        \MicroChaos_Log::warning('Second');
        \MicroChaos_Log::error('Third');
        \MicroChaos_Log::success('Fourth');

        $this->assertCount(4, $spy->calls);
        $this->assertEquals('log', $spy->calls[0]['method']);
        $this->assertEquals('warning', $spy->calls[1]['method']);
        $this->assertEquals('error', $spy->calls[2]['method']);
        $this->assertEquals('success', $spy->calls[3]['method']);
    }
}
