<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Data')):

final class PCC_Data {

    /** @var PCC_API */
    private $api;

    /** @var PCC_Cache|null */
    private $cache;

    public function __construct(PCC_API $api, $cache = null) {
        $this->api = $api;
        $this->cache = ($cache instanceof PCC_Cache) ? $cache : null;
    }

    /**
     * Get upcoming event instances.
     *
     * @param bool $force_refresh Bypass cache.
     * @param int  $limit How many instances to request from PCO.
     * @return array|\WP_Error
     */
    public function get_events($force_refresh = false, $limit = 10) {
        $limit = max(1, (int)$limit);
        $cache_key = 'events_' . $limit;

        if (!$force_refresh && $this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }

        // NOTE: Using event_instances (Calendar).
        // We include event so we can render event title/url.
        $params = array(
            'per_page' => $limit,
            'include'  => 'event',
        );

        $json = $this->api->get_json('/calendar/v2/event_instances', $params);

        if (!is_wp_error($json) && $this->cache) {
            $this->cache->set($cache_key, $json, 10 * MINUTE_IN_SECONDS);
        }

        return $json;
    }

    /**
     * Get Publishing episodes (sermons).
     */
    public function get_sermons($force_refresh = false, $limit = 10) {
        $limit = max(1, (int)$limit);
        $cache_key = 'sermons_' . $limit;

        if (!$force_refresh && $this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $params = array(
            'per_page' => $limit,
        );

        $json = $this->api->get_json('/publishing/v2/episodes', $params);

        if (!is_wp_error($json) && $this->cache) {
            $this->cache->set($cache_key, $json, 30 * MINUTE_IN_SECONDS);
        }

        return $json;
    }

    /**
     * Get Groups.
     */
    public function get_groups($force_refresh = false, $limit = 20) {
        $limit = max(1, (int)$limit);
        $cache_key = 'groups_' . $limit;

        if (!$force_refresh && $this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $params = array(
            'per_page' => $limit,
        );

        $json = $this->api->get_json('/groups/v2/groups', $params);

        if (!is_wp_error($json) && $this->cache) {
            $this->cache->set($cache_key, $json, 60 * MINUTE_IN_SECONDS);
        }

        return $json;
    }
}

endif;
