<?php
/**
 * Authentication Manager Component
 *
 * Centralizes authentication logic for MicroChaos load testing.
 * Handles WordPress cookie-based auth, HTTP Basic auth, and cookie utilities.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Authentication Manager
 *
 * Provides static utilities for authentication operations.
 * Handles both WordPress cookie authentication and HTTP Basic authentication.
 */
class MicroChaos_Authentication_Manager {

    // ==================== WordPress Cookie Authentication ====================

    /**
     * Authenticate a single user by email and retrieve cookies
     *
     * @param string $email User email address
     * @return array|null Array of WP_Http_Cookie objects, or null if user not found
     */
    public static function authenticate_user(string $email): ?array {
        $user = get_user_by('email', $email);
        if (!$user) {
            return null;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        $cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));

        \WP_CLI::log("🔐 Authenticated as {$user->user_login}");

        return $cookies;
    }

    /**
     * Authenticate multiple users and retrieve session cookies for each
     *
     * @param array $emails Array of user email addresses
     * @return array Array of cookie session arrays (multi-auth format)
     */
    public static function authenticate_users(array $emails): array {
        $auth_sessions = [];

        foreach ($emails as $email) {
            $user = get_user_by('email', $email);
            if (!$user) {
                \WP_CLI::warning("User with email {$email} not found. Skipping.");
                continue;
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            $session_cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));
            $auth_sessions[] = $session_cookies;

            \WP_CLI::log("🔐 Added session for {$user->user_login}");
        }

        return $auth_sessions;
    }

    // ==================== HTTP Basic Authentication ====================

    /**
     * Parse auth string in username@domain format
     *
     * @param string $auth Auth string (e.g., "username@domain.com")
     * @return array|null ['username' => string, 'domain' => string] or null if invalid format
     */
    public static function parse_auth_string(string $auth): ?array {
        if (strpos($auth, '@') === false) {
            return null;
        }

        list($username, $domain) = explode('@', $auth, 2);

        return [
            'username' => $username,
            'domain' => $domain
        ];
    }

    /**
     * Create HTTP Basic Auth header array
     *
     * @param string $username Username for Basic auth
     * @param string $password Password (defaults to 'password')
     * @return array ['Authorization' => 'Basic base64(username:password)']
     */
    public static function create_basic_auth_headers(string $username, string $password = 'password'): array {
        return [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ];
    }

    // ==================== Cookie Utilities ====================

    /**
     * Detect if cookies array is multi-auth format (array of session arrays)
     *
     * Multi-auth format: [[WP_Http_Cookie, ...], [WP_Http_Cookie, ...], ...]
     * Single-auth format: [WP_Http_Cookie, WP_Http_Cookie, ...]
     *
     * @param array $cookies Cookie array to check
     * @return bool True if multi-auth format
     */
    public static function is_multi_auth(array $cookies): bool {
        return is_array($cookies) && isset($cookies[0]) && is_array($cookies[0]);
    }

    /**
     * Select a random session from multi-auth cookie array
     *
     * @param array $sessions Array of session arrays
     * @return array Single session's cookies
     */
    public static function select_random_session(array $sessions): array {
        return $sessions[array_rand($sessions)];
    }

    /**
     * Format cookies for cURL requests (semicolon-separated string)
     *
     * @param array $cookies Array of WP_Http_Cookie objects
     * @return string Cookie string in "name1=value1; name2=value2" format
     */
    public static function format_for_curl(array $cookies): string {
        return implode('; ', array_map(
            function($cookie) {
                return "{$cookie->name}={$cookie->value}";
            },
            $cookies
        ));
    }

    /**
     * Format cookies for wp_remote_request (passthrough)
     *
     * wp_remote_request expects the WP_Http_Cookie array directly,
     * so this is a passthrough for API consistency.
     *
     * @param array $cookies Array of WP_Http_Cookie objects
     * @return array Same cookie array (passthrough)
     */
    public static function format_for_wp_remote(array $cookies): array {
        return $cookies;
    }
}
