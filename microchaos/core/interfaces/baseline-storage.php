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
    public function save($key, $data, $ttl = null);

    /**
     * Retrieve baseline data by key
     *
     * @param string $key Storage key
     * @return mixed|null Stored data or null if not found
     */
    public function get($key);

    /**
     * Check if a baseline exists
     *
     * @param string $key Storage key
     * @return bool True if exists, false otherwise
     */
    public function exists($key);

    /**
     * Delete a baseline
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete($key);
}
