<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Data {

    /** @var PCC_API */
    private $api;

    /** @var PCC_Cache|null */
    private $cache;

    /**
     * ✅ cache dibuat OPTIONAL agar tidak fatal kalau dipanggil 1 arg dari file lain
     */
    public function __construct(PCC_API $api, $cache = null) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    public function get_events($use_cache = true, $limit = 10) {
        $limit = max(1, (int)$limit);

        // event_instances lebih cocok untuk starts_at/ends_at
        return $this->api->get_json('/calendar/v2/event_instances', array(
            'per_page' => $limit,
            'include'  => 'event',
        ));
    }

    public function get_sermons($use_cache = true, $limit = 10) {
        $limit = max(1, (int)$limit);

        return $this->api->get_json('/publishing/v2/episodes', array(
            'per_page' => $limit,
        ));
    }

    public function get_groups($use_cache = true, $limit = 20) {
        $limit = max(1, (int)$limit);

        return $this->api->get_json('/groups/v2/groups', array(
            'per_page' => $limit,
        ));
    }
}