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
     * Get upcoming event instances (RAW JSON) - used by admin probe/cron/backward compat.
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

    /* ======================================================
     * CALENDAR RANGE/MONTH (Normalized)
     * ====================================================== */

    public function get_event_instances_in_range(DateTimeImmutable $start, DateTimeImmutable $end, $public_only = true, $force_refresh = false) {
        $public_only = (bool)$public_only;

        $start_utc = $start->setTimezone(new DateTimeZone('UTC'))->format('c');
        $end_utc   = $end->setTimezone(new DateTimeZone('UTC'))->format('c');

        $cache_key = 'event_instances_range_' . md5($start_utc . '|' . $end_utc . '|public=' . ($public_only ? '1' : '0'));

        if (!$force_refresh && $this->cache) {
            $cached = $this->cache->get($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $params = array(
            'where[starts_at][gte]' => $start_utc,
            'where[starts_at][lt]'  => $end_utc,
            'order'                 => 'starts_at',
            'include'               => 'event',
            'per_page'              => 100,
        );

        $resp = $this->api->get_all('/calendar/v2/event_instances', $params);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $items = $this->normalize_event_instances_response($resp, $public_only);

        if ($this->cache) {
            $this->cache->set($cache_key, $items, 10 * MINUTE_IN_SECONDS);
        }

        return $items;
    }

    public function get_event_instances_for_month($year, $month, $public_only = true, $force_refresh = false) {
        $year  = max(1970, (int)$year);
        $month = max(1, min(12, (int)$month));
        $public_only = (bool)$public_only;

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $end   = $start->modify('first day of next month');

        return $this->get_event_instances_in_range($start, $end, $public_only, $force_refresh);
    }

    private function normalize_event_instances_response($resp, $public_only) {
        $public_only = (bool)$public_only;

        $data = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : array();
        $included = isset($resp['included']) && is_array($resp['included']) ? $resp['included'] : array();

        $events_by_id = array();
        foreach ($included as $inc) {
            if (!is_array($inc)) { continue; }
            if (!isset($inc['type']) || $inc['type'] !== 'Event') { continue; }
            if (!isset($inc['id'])) { continue; }
            $events_by_id[(string)$inc['id']] = $inc;
        }

        $out = array();
        foreach ($data as $row) {
            if (!is_array($row)) { continue; }

            $inst_id = isset($row['id']) ? (string)$row['id'] : '';
            $attrs   = isset($row['attributes']) && is_array($row['attributes']) ? $row['attributes'] : array();

            $starts_at = isset($attrs['starts_at']) ? (string)$attrs['starts_at'] : '';
            $ends_at   = isset($attrs['ends_at']) ? (string)$attrs['ends_at'] : '';

            $event_id = '';
            if (isset($row['relationships']['event']['data']['id'])) {
                $event_id = (string)$row['relationships']['event']['data']['id'];
            }

            $event = ($event_id && isset($events_by_id[$event_id])) ? $events_by_id[$event_id] : null;
            $event_attrs = ($event && isset($event['attributes']) && is_array($event['attributes'])) ? $event['attributes'] : array();

            if ($public_only && !$this->is_event_public($event_attrs)) {
                continue;
            }

            $title = $this->first_non_empty($event_attrs, array('name', 'title'), '');
            if ($title === '') {
                $title = $this->first_non_empty($attrs, array('name', 'title'), '');
            }

            $description = $this->first_non_empty($event_attrs, array('description', 'summary', 'details'), '');
            $location    = $this->first_non_empty($event_attrs, array('location', 'event_location', 'address'), '');

            $image_url = $this->first_non_empty($event_attrs, array('image_url', 'logo_url'), '');

            $url = $this->first_non_empty($event_attrs, array('church_center_url', 'public_url', 'url', 'website_url'), '');

            $date_key = '';
            if ($starts_at) {
                $ts = strtotime($starts_at);
                if ($ts) {
                    $date_key = function_exists('wp_date') ? wp_date('Y-m-d', $ts) : gmdate('Y-m-d', $ts);
                }
            }

            $out[] = array(
                'instance_id' => $inst_id,
                'event_id'    => $event_id,
                'title'       => $title,
                'description' => $description,
                'location'    => $location,
                'image_url'   => $image_url,
                'url'         => $url,
                'starts_at'   => $starts_at,
                'ends_at'     => $ends_at,
                'date'        => $date_key,
            );
        }

        return $out;
    }

    private function first_non_empty($arr, $keys, $default = '') {
        if (!is_array($arr)) { return $default; }
        foreach ($keys as $k) {
            if (isset($arr[$k]) && is_string($arr[$k]) && trim($arr[$k]) !== '') {
                return (string)$arr[$k];
            }
        }
        return $default;
    }

    private function is_event_public($event_attrs) {
        if (!is_array($event_attrs) || empty($event_attrs)) {
            return true;
        }

        $bool_keys = array(
            'published',
            'is_published',
            'visible_in_church_center',
            'show_in_church_center',
            'public',
            'is_public',
        );
        foreach ($bool_keys as $k) {
            if (array_key_exists($k, $event_attrs)) {
                return (bool)$event_attrs[$k];
            }
        }

        $hide_keys = array(
            'hide_from_church_center',
            'hidden_from_church_center',
            'hide_from_public',
            'hidden',
            'is_hidden',
        );
        foreach ($hide_keys as $k) {
            if (array_key_exists($k, $event_attrs)) {
                return !(bool)$event_attrs[$k];
            }
        }

        $enum_keys = array(
            'visibility',
            'church_center_visibility',
        );
        foreach ($enum_keys as $k) {
            if (isset($event_attrs[$k]) && is_string($event_attrs[$k])) {
                $v = strtolower(trim($event_attrs[$k]));
                if ($v === 'published' || $v === 'public' || $v === 'visible') {
                    return true;
                }
                if ($v === 'hidden' || $v === 'unpublished' || $v === 'private') {
                    return false;
                }
            }
        }

        return true;
    }
}

endif;