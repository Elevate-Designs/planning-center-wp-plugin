<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Data {

    /** @var PCC_API */
    private $api;

    /** @var PCC_Cache */
    private $cache;

    public function __construct($api, $cache) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /**
     * Upcoming event instances (Calendar).
     * Endpoint: /calendar/v2/event_instances (commonly used with filter=future).
     */
    public function get_events($force_refresh = false, $limit = 25) {
        $cached = $this->cache->get(PCC_Cache::KEY_EVENTS);
        if (!$force_refresh && is_array($cached)) {
            return $cached;
        }

        $params = array(
            'filter'   => 'future',
            'per_page' => intval($limit),
            // Planning Center uses JSON:API includes; if you get no included data, remove this line.
            'include'  => 'event',
        );

        $json = $this->api->get_all('/calendar/v2/event_instances', $params, 5);
        if (is_wp_error($json)) {
            return $json;
        }

        $this->cache->set(PCC_Cache::KEY_EVENTS, $json);
        return $json;
    }

    /**
     * Sermons (Publishing Episodes).
     * Endpoint: /publishing/v2/episodes
     */
    public function get_sermons($force_refresh = false, $limit = 20) {
        $cached = $this->cache->get(PCC_Cache::KEY_SERMONS);
        if (!$force_refresh && is_array($cached)) {
            return $cached;
        }

        $params = array(
            'per_page' => intval($limit),
        );

        $json = $this->api->get_all('/publishing/v2/episodes', $params, 5);
        if (is_wp_error($json)) {
            return $json;
        }

        $this->cache->set(PCC_Cache::KEY_SERMONS, $json);
        return $json;
    }

    /**
     * Groups.
     * Endpoint: /groups/v2/groups
     */
    public function get_groups($force_refresh = false, $limit = 50) {
        $cached = $this->cache->get(PCC_Cache::KEY_GROUPS);
        if (!$force_refresh && is_array($cached)) {
            return $cached;
        }

        $params = array(
            'per_page' => intval($limit),
        );

        $json = $this->api->get_all('/groups/v2/groups', $params, 5);
        if (is_wp_error($json)) {
            return $json;
        }

        $this->cache->set(PCC_Cache::KEY_GROUPS, $json);
        return $json;
    }
}
