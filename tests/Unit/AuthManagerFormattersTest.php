<?php

declare(strict_types=1);

namespace MicroChaos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for MicroChaos_Authentication_Manager formatter methods
 *
 * Tests pure PHP methods only. WordPress-dependent methods
 * (authenticate_user, authenticate_users) are skipped.
 */
class AuthManagerFormattersTest extends TestCase
{
    // =========================================================================
    // parse_auth_string() Tests
    // =========================================================================

    #[Test]
    public function parse_auth_string_extracts_username_and_domain(): void
    {
        $result = \MicroChaos_Authentication_Manager::parse_auth_string('admin@example.com');

        $this->assertIsArray($result);
        $this->assertEquals('admin', $result['username']);
        $this->assertEquals('example.com', $result['domain']);
    }

    #[Test]
    public function parse_auth_string_handles_subdomain(): void
    {
        $result = \MicroChaos_Authentication_Manager::parse_auth_string('user@staging.example.com');

        $this->assertEquals('user', $result['username']);
        $this->assertEquals('staging.example.com', $result['domain']);
    }

    #[Test]
    public function parse_auth_string_returns_null_without_at_symbol(): void
    {
        $result = \MicroChaos_Authentication_Manager::parse_auth_string('invalid-format');

        $this->assertNull($result);
    }

    #[Test]
    public function parse_auth_string_splits_on_first_at_only(): void
    {
        // Edge case: email-like format "user@domain@extra"
        $result = \MicroChaos_Authentication_Manager::parse_auth_string('user@domain@extra');

        $this->assertEquals('user', $result['username']);
        $this->assertEquals('domain@extra', $result['domain']);
    }

    // =========================================================================
    // create_basic_auth_headers() Tests
    // =========================================================================

    #[Test]
    public function create_basic_auth_headers_uses_default_password(): void
    {
        $headers = \MicroChaos_Authentication_Manager::create_basic_auth_headers('testuser');

        $this->assertArrayHasKey('Authorization', $headers);

        // Decode and verify: testuser:password
        $expected = 'Basic ' . base64_encode('testuser:password');
        $this->assertEquals($expected, $headers['Authorization']);
    }

    #[Test]
    public function create_basic_auth_headers_uses_custom_password(): void
    {
        $headers = \MicroChaos_Authentication_Manager::create_basic_auth_headers('admin', 'secretpass');

        $expected = 'Basic ' . base64_encode('admin:secretpass');
        $this->assertEquals($expected, $headers['Authorization']);
    }

    #[Test]
    public function create_basic_auth_headers_handles_special_characters(): void
    {
        $headers = \MicroChaos_Authentication_Manager::create_basic_auth_headers('user', 'pass:with@special!chars');

        // Should still be valid base64
        $encoded = $headers['Authorization'];
        $this->assertStringStartsWith('Basic ', $encoded);

        // Decode to verify
        $decoded = base64_decode(substr($encoded, 6));
        $this->assertEquals('user:pass:with@special!chars', $decoded);
    }

    // =========================================================================
    // is_multi_auth() Tests
    // =========================================================================

    #[Test]
    public function is_multi_auth_returns_true_for_nested_arrays(): void
    {
        // Multi-auth format: array of session arrays
        $multiAuth = [
            [new \WP_Http_Cookie('session1', 'value1')],
            [new \WP_Http_Cookie('session2', 'value2')],
        ];

        $this->assertTrue(\MicroChaos_Authentication_Manager::is_multi_auth($multiAuth));
    }

    #[Test]
    public function is_multi_auth_returns_false_for_flat_array(): void
    {
        // Single-auth format: flat array of cookies
        $singleAuth = [
            new \WP_Http_Cookie('cookie1', 'value1'),
            new \WP_Http_Cookie('cookie2', 'value2'),
        ];

        $this->assertFalse(\MicroChaos_Authentication_Manager::is_multi_auth($singleAuth));
    }

    #[Test]
    public function is_multi_auth_returns_false_for_empty_array(): void
    {
        $this->assertFalse(\MicroChaos_Authentication_Manager::is_multi_auth([]));
    }

    // =========================================================================
    // select_random_session() Tests
    // =========================================================================

    #[Test]
    public function select_random_session_returns_array_from_sessions(): void
    {
        $sessions = [
            ['cookie1' => 'session1'],
            ['cookie2' => 'session2'],
            ['cookie3' => 'session3'],
        ];

        $selected = \MicroChaos_Authentication_Manager::select_random_session($sessions);

        $this->assertIsArray($selected);
        $this->assertContains($selected, $sessions);
    }

    #[Test]
    public function select_random_session_returns_only_element_from_single_session(): void
    {
        $sessions = [
            ['only_session' => 'value'],
        ];

        $selected = \MicroChaos_Authentication_Manager::select_random_session($sessions);

        $this->assertEquals(['only_session' => 'value'], $selected);
    }

    // =========================================================================
    // format_for_curl() Tests
    // =========================================================================

    #[Test]
    public function format_for_curl_creates_semicolon_separated_string(): void
    {
        $cookies = [
            new \WP_Http_Cookie('session_id', 'abc123'),
            new \WP_Http_Cookie('user_token', 'xyz789'),
        ];

        $result = \MicroChaos_Authentication_Manager::format_for_curl($cookies);

        $this->assertEquals('session_id=abc123; user_token=xyz789', $result);
    }

    #[Test]
    public function format_for_curl_handles_single_cookie(): void
    {
        $cookies = [
            new \WP_Http_Cookie('single', 'value'),
        ];

        $result = \MicroChaos_Authentication_Manager::format_for_curl($cookies);

        $this->assertEquals('single=value', $result);
    }

    #[Test]
    public function format_for_curl_handles_empty_array(): void
    {
        $result = \MicroChaos_Authentication_Manager::format_for_curl([]);

        $this->assertEquals('', $result);
    }

    // =========================================================================
    // format_for_wp_remote() Tests
    // =========================================================================

    #[Test]
    public function format_for_wp_remote_is_passthrough(): void
    {
        $cookies = [
            new \WP_Http_Cookie('cookie1', 'value1'),
            new \WP_Http_Cookie('cookie2', 'value2'),
        ];

        $result = \MicroChaos_Authentication_Manager::format_for_wp_remote($cookies);

        // Should return exact same array (passthrough)
        $this->assertSame($cookies, $result);
    }
}
