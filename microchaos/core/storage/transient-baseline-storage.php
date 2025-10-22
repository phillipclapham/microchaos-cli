<?php
/**
 * Transient Baseline Storage Implementation
 *
 * Stores baseline data using WordPress transients with file-based fallback.
 * Transients are preferred for performance, but files are used when transients
 * are unavailable or unreliable.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Transient-based baseline storage implementation
 */
class MicroChaos_Transient_Baseline_Storage implements MicroChaos_Baseline_Storage {
    /**
     * Prefix for transient keys
     *
     * @var string
     */
    private $prefix;

    /**
     * Default TTL in seconds (30 days)
     *
     * @var int
     */
    private $default_ttl = MicroChaos_Constants::BASELINE_TTL;

    /**
     * Directory for file-based fallback storage
     *
     * @var string
     */
    private $storage_dir;

    /**
     * Constructor
     *
     * @param string $prefix Prefix for storage keys (e.g., 'microchaos_baseline', 'microchaos_resource_baseline')
     */
    public function __construct($prefix = 'microchaos_baseline') {
        $this->prefix = rtrim($prefix, '_') . '_';
        $this->storage_dir = WP_CONTENT_DIR . '/microchaos/baselines';
    }

    /**
     * Save baseline data with a given key
     *
     * @param string $key Storage key (will be sanitized)
     * @param mixed $data Data to store
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success status
     */
    public function save($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->default_ttl;
        $sanitized_key = $this->sanitize_key($key);

        // Try transients first
        if (function_exists('set_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $result = set_transient($transient_key, $data, $ttl);

            if ($result) {
                return true;
            }
        }

        // Fallback to file storage
        return $this->save_to_file($sanitized_key, $data);
    }

    /**
     * Retrieve baseline data by key
     *
     * @param string $key Storage key
     * @return mixed|null Stored data or null if not found
     */
    public function get($key) {
        $sanitized_key = $this->sanitize_key($key);

        // Try transients first
        if (function_exists('get_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $data = get_transient($transient_key);

            if ($data !== false) {
                return $data;
            }
        }

        // Fallback to file storage
        return $this->load_from_file($sanitized_key);
    }

    /**
     * Check if a baseline exists
     *
     * @param string $key Storage key
     * @return bool True if exists, false otherwise
     */
    public function exists($key) {
        $sanitized_key = $this->sanitize_key($key);

        // Check transient
        if (function_exists('get_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            if (get_transient($transient_key) !== false) {
                return true;
            }
        }

        // Check file storage
        $filepath = $this->get_file_path($sanitized_key);
        return file_exists($filepath);
    }

    /**
     * Delete a baseline
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete($key) {
        $sanitized_key = $this->sanitize_key($key);
        $success = true;

        // Delete transient
        if (function_exists('delete_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $success = delete_transient($transient_key);
        }

        // Delete file if exists
        $filepath = $this->get_file_path($sanitized_key);
        if (file_exists($filepath)) {
            $success = unlink($filepath) && $success;
        }

        return $success;
    }

    /**
     * Sanitize storage key
     *
     * @param string $key Raw key
     * @return string Sanitized key
     */
    private function sanitize_key($key) {
        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }

        // Fallback sanitization if WordPress functions unavailable
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }

    /**
     * Get file path for a given key
     *
     * @param string $sanitized_key Already sanitized key
     * @return string Full file path
     */
    private function get_file_path($sanitized_key) {
        $filename = sanitize_file_name($this->prefix . $sanitized_key . '.json');
        return $this->storage_dir . '/' . $filename;
    }

    /**
     * Save data to file storage
     *
     * @param string $sanitized_key Already sanitized key
     * @param mixed $data Data to store
     * @return bool Success status
     */
    private function save_to_file($sanitized_key, $data) {
        // Create directory if needed
        if (!file_exists($this->storage_dir)) {
            if (!mkdir($this->storage_dir, 0755, true)) {
                return false;
            }
        }

        $filepath = $this->get_file_path($sanitized_key);
        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        if ($json_data === false) {
            return false;
        }

        return file_put_contents($filepath, $json_data) !== false;
    }

    /**
     * Load data from file storage
     *
     * @param string $sanitized_key Already sanitized key
     * @return mixed|null Stored data or null if not found
     */
    private function load_from_file($sanitized_key) {
        $filepath = $this->get_file_path($sanitized_key);

        if (!file_exists($filepath)) {
            return null;
        }

        $json_data = file_get_contents($filepath);
        if ($json_data === false) {
            return null;
        }

        $data = json_decode($json_data, true);
        return $data !== null ? $data : null;
    }
}
