<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Data {

    /** @var PCC_API */
    private $api;

    /** @var PCC_Cache|null */
    private $cache;

    public function __construct(PCC_API $api, $cache = null) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /**
     * ======================================================
     * EVENTS (Calendar)
     * ======================================================
     */
    public function get_events($force = false, $limit = 10) {

        // Planning Center Calendar endpoint (BENAR)
        $path = '/calendar/v2/event_instances';

        $params = [
            'include' => 'event',
            'per_page' => min(25, max(1, (int)$limit)),
        ];

        // optional cache
        $cache_key = 'events_' . $limit;

        if (!$force && $this->cache && ($cached = $this->cache->get($cache_key))) {
            return $cached;
        }

        $json = $this->api->get_all($path, $params, 5);

        if (!is_wp_error($json) && $this->cache) {
            $this->cache->set($cache_key, $json, 300); // 5 min
        }

        return $json;
    }

    /**
     * ======================================================
     * SERMONS (Publishing)
     * ======================================================
     */
    public function get_sermons($force = false, $limit = 10) {

        $path = '/publishing/v2/episodes';

        $params = [
            'per_page' => min(25, max(1, (int)$limit)),
        ];

        return $this->api->get_all($path, $params, 3);
    }

    /**
     * ======================================================
     * GROUPS
     * ======================================================
     */
    public function get_groups($force = false, $limit = 20) {

        $path = '/groups/v2/groups';

        $params = [
            'per_page' => min(25, max(1, (int)$limit)),
        ];

        return $this->api->get_all($path, $params, 3);
    }
}