<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Data {

    private $api;
    private $cache;

    public function __construct($api, $cache = null) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /* ======================================================
     * EVENTS (Calendar)
     * ====================================================== */
    public function get_events($force = false, $limit = 10) {
        $limit = max(1, (int)$limit);

        $path = '/calendar/v2/event_instances';
        $params = array(
            'include'  => 'event',
            'per_page' => min(25, $limit),
        );

        $cache_key = 'pcc_events_' . $limit;

        // ✅ Cache read (only if available)
        if (!$force && $this->cache && method_exists($this->cache, 'get')) {
            $cached = $this->cache->get($cache_key);
            if (!empty($cached)) {
                return $cached;
            }
        }

        // Fetch from API (paged)
        $json = $this->api->get_all($path, $params, 5);

        // ✅ Cache write (only if available)
        if (!is_wp_error($json) && $this->cache && method_exists($this->cache, 'set')) {
            $this->cache->set($cache_key, $json, 300); // 5 min
        }

        return $json;
    }

    /* ======================================================
     * SERMONS (Publishing Episodes)
     * ====================================================== */
    public function get_sermons($force = false, $limit = 10) {
        $limit = max(1, (int)$limit);

        $path = '/publishing/v2/episodes';
        $params = array(
            'per_page' => min(25, $limit),
        );

        return $this->api->get_all($path, $params, 3);
    }

    /* ======================================================
     * GROUPS
     * ====================================================== */
    public function get_groups($force = false, $limit = 20) {
        $limit = max(1, (int)$limit);

        $path = '/groups/v2/groups';
        $params = array(
            'per_page' => min(25, $limit),
        );

        return $this->api->get_all($path, $params, 3);
    }
}