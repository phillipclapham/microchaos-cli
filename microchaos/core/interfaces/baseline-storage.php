<?php
/**
 * Baseline Storage Interface
 *
 * Defines the contract for storing and retrieving baseline data
 * for load test comparisons.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Interface for baseline storage operations
 */
interface MicroChaos_Baseline_Storage {
    /**
     * Save baseline data with a given key
     *
     * @param string $key Storage key (will be sanitized)
     * @param mixed $data Data to store
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success status
     */
    public function save(string $key, $data, ?int $ttl = null): bool;

    /**
     * Retrieve baseline data by key
     *
     * @param string $key Storage key
     * @return mixed|null Stored data or null if not found
     */
    public function get(string $key);

    /**
     * Check if a baseline exists
     *
     * @param string $key Storage key
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key): bool;

    /**
     * Delete a baseline
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool;
}
