<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Cache')):

final class PCC_Cache {

    /**
     * Prefix all keys to avoid collisions.
     */
    private function prefix($key) {
        return 'pcc_' . ltrim((string)$key, '_');
    }

    public function get($key) {
        $key = $this->prefix($key);

        // Prefer object cache if available, otherwise fall back to transients.
        if (function_exists('wp_cache_get')) {
            $val = wp_cache_get($key, defined('PCC_CACHE_GROUP') ? PCC_CACHE_GROUP : 'pcc');
            if ($val !== false) {
                return $val;
            }
        }

        $val = get_transient($key);
        return ($val === false) ? null : $val;
    }

    public function set($key, $value, $ttl = 600) {
        $key = $this->prefix($key);
        $ttl = max(1, (int)$ttl);

        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, defined('PCC_CACHE_GROUP') ? PCC_CACHE_GROUP : 'pcc', $ttl);
        }

        set_transient($key, $value, $ttl);
    }

    /**
     * Clear all PCC transients.
     */
    public function clear_all() {
        global $wpdb;

        $prefix = $wpdb->esc_like('_transient_pcc_') . '%';
        $prefix_timeout = $wpdb->esc_like('_transient_timeout_pcc_') . '%';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $prefix,
                $prefix_timeout
            )
        );
    }

    /**
     * Backward-compat alias (some code used flush_all()).
     */
    public function flush_all() {
        $this->clear_all();
    }
}

endif;
