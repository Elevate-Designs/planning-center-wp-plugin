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

    public function __construct($api, $cache = null) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /**
     * Safe cache get (optional cache layer)
     */
    private function cache_get($key) {
        if ($this->cache && is_object($this->cache) && method_exists($this->cache, 'get')) {
            return $this->cache->get($key);
        }
        return null;
    }

    /**
     * Safe cache set (optional cache layer)
     */
    private function cache_set($key, $value, $ttl = 600) {
        if ($this->cache && is_object($this->cache) && method_exists($this->cache, 'set')) {
            $this->cache->set($key, $value, $ttl);
        }
    }

    /**
     * Backward-compatible method.
     * Returns upcoming event instances (default 50) within next 2 months.
     *
     * @return array|\WP_Error
     */
    public function get_events($limit = 50, $public_only = true) {
        $limit = max(1, (int)$limit);

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $start = new DateTimeImmutable('now', $tz);
        $end   = $start->modify('+2 months');

        $items = $this->get_event_instances_in_range($start, $end, $public_only);
        if (is_wp_error($items)) {
            return $items;
        }

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }

    /**
     * Fetch event instances between two DateTimeImmutable boundaries.
     * Uses /calendar/v2/event_instances with where[starts_at][gte] and where[starts_at][lt]
     *
     * @return array|\WP_Error
     */
    public function get_event_instances_in_range(DateTimeImmutable $start, DateTimeImmutable $end, $public_only = true) {
        $public_only = (bool)$public_only;

        $start_utc = $start->setTimezone(new DateTimeZone('UTC'))->format('c');
        $end_utc   = $end->setTimezone(new DateTimeZone('UTC'))->format('c');

        $cache_key = 'event_instances_range_' . md5($start_utc . '|' . $end_utc . '|public=' . ($public_only ? '1' : '0'));
        $cached = $this->cache_get($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $params = array(
            'where[starts_at][gte]' => $start_utc,
            'where[starts_at][lt]'  => $end_utc,
            'order'                 => 'starts_at',
            'include'               => 'event',
            'per_page'              => 100,
        );

        $resp = $this->api->get_all('calendar/v2/event_instances', $params);

        // ✅ IMPORTANT: handle API errors to avoid fatal
        if (is_wp_error($resp)) {
            return $resp;
        }

        $items = $this->normalize_event_instances_response($resp, $public_only);

        $this->cache_set($cache_key, $items, 600);
        return $items;
    }

    /**
     * Fetch instances for specific month (year + month number 1..12), localized to WP timezone.
     *
     * @return array|\WP_Error
     */
    public function get_event_instances_for_month($year, $month, $public_only = true) {
        $year  = max(1970, (int)$year);
        $month = max(1, min(12, (int)$month));
        $public_only = (bool)$public_only;

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $end   = $start->modify('first day of next month');

        return $this->get_event_instances_in_range($start, $end, $public_only);
    }

    /**
     * =====================================================
     * GROUPS
     * =====================================================
     * @return array|\WP_Error
     */
    public function get_groups($limit = 50, $public_only = true) {
        $limit = max(1, min(200, (int)$limit));
        $public_only = (bool)$public_only;

        $cache_key = 'groups_' . md5('limit=' . $limit . '|public=' . ($public_only ? '1' : '0'));
        $cached = $this->cache_get($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $params = array(
            'per_page' => 100,
            'order'    => 'name',
        );

        $resp = $this->api->get_all('groups/v2/groups', $params);

        // ✅ IMPORTANT: handle API errors to avoid fatal
        if (is_wp_error($resp)) {
            return $resp;
        }

        $items = $this->normalize_groups_response($resp, $public_only);

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        $this->cache_set($cache_key, $items, 600);
        return $items;
    }

    /**
     * =====================================================
     * SERMONS (Publishing Episodes)
     * =====================================================
     * @return array|\WP_Error
     */
    public function get_sermons($limit = 20, $public_only = true) {
        $limit = max(1, min(200, (int)$limit));
        $public_only = (bool)$public_only;

        $cache_key = 'sermons_' . md5('limit=' . $limit . '|public=' . ($public_only ? '1' : '0'));
        $cached = $this->cache_get($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $params = array(
            'per_page' => 100,
            'order'    => '-published_at',
        );

        $resp = $this->api->get_all('publishing/v2/episodes', $params);

        // ✅ IMPORTANT: handle API errors to avoid fatal
        if (is_wp_error($resp)) {
            return $resp;
        }

        $items = $this->normalize_sermons_response($resp, $public_only);

        // Fallback sort (desc)
        usort($items, function($a, $b) {
            return strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? ''));
        });

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        $this->cache_set($cache_key, $items, 600);
        return $items;
    }

    /**
     * Normalize JSON:API response from event_instances?include=event into flat array for templates/UI.
     */
    private function normalize_event_instances_response($resp, $public_only) {
        $public_only = (bool)$public_only;

        if (!is_array($resp)) {
            return array();
        }

        $data = (isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : array();
        $included = (isset($resp['included']) && is_array($resp['included'])) ? $resp['included'] : array();

        // Map included events by id.
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
            $attrs   = (isset($row['attributes']) && is_array($row['attributes'])) ? $row['attributes'] : array();

            $starts_at = isset($attrs['starts_at']) ? (string)$attrs['starts_at'] : '';
            $ends_at   = isset($attrs['ends_at']) ? (string)$attrs['ends_at'] : '';

            // Resolve related event
            $event_id = '';
            if (isset($row['relationships']['event']['data']['id'])) {
                $event_id = (string)$row['relationships']['event']['data']['id'];
            }

            $event = ($event_id && isset($events_by_id[$event_id])) ? $events_by_id[$event_id] : null;
            $event_attrs = ($event && isset($event['attributes']) && is_array($event['attributes'])) ? $event['attributes'] : array();

            // Public-only filter (heuristic)
            if ($public_only && !$this->is_event_public($event_attrs)) {
                continue;
            }

            $title = $this->first_non_empty($event_attrs, array('name', 'title'), '');
            if ($title === '') {
                $title = $this->first_non_empty($attrs, array('name', 'title'), '');
            }

            $description = $this->first_non_empty($event_attrs, array('description', 'summary', 'details'), '');
            $location    = $this->first_non_empty($event_attrs, array('location', 'event_location', 'address'), '');

            $image_url = $this->pick_image_url($attrs);
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

    private function normalize_groups_response($resp, $public_only) {
        $public_only = (bool)$public_only;

        if (!is_array($resp)) {
            return array();
        }

        $data = (isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : array();
        $out = array();

        foreach ($data as $row) {
            if (!is_array($row)) { continue; }

            $id = isset($row['id']) ? (string)$row['id'] : '';
            $attrs = (isset($row['attributes']) && is_array($row['attributes'])) ? $row['attributes'] : array();

            if ($public_only && !$this->is_group_public($attrs)) {
                continue;
            }

            $title = $this->first_non_empty($attrs, array('name', 'title'), '');
            $description = $this->first_non_empty($attrs, array('description', 'public_description', 'summary'), '');

            $image_url = $this->first_non_empty($attrs, array(
                'image_url','photo_url','avatar_url','cover_image_url','header_image_url','logo_url','thumbnail_url'
            ), '');

            $url = $this->first_non_empty($attrs, array(
                'public_church_center_web_url',
                'public_church_center_url',
                'church_center_web_url',
                'church_center_url',
                'public_url',
                'url',
                'web_url',
            ), '');

            if ($public_only && $url === '' && $title !== '') {
                continue;
            }

            $out[] = array(
                'group_id'    => $id,
                'title'       => $title,
                'description' => $description,
                'image_url'   => $image_url,
                'url'         => $url,
            );
        }

        return $out;
    }

    private function normalize_sermons_response($resp, $public_only) {
        $public_only = (bool)$public_only;

        if (!is_array($resp)) {
            return array();
        }

        $data = (isset($resp['data']) && is_array($resp['data'])) ? $resp['data'] : array();
        $out = array();

        foreach ($data as $row) {
            if (!is_array($row)) { continue; }

            $id = isset($row['id']) ? (string)$row['id'] : '';
            $attrs = (isset($row['attributes']) && is_array($row['attributes'])) ? $row['attributes'] : array();

            if ($public_only && !$this->is_episode_public($attrs)) {
                continue;
            }

            $title = $this->first_non_empty($attrs, array('title', 'name'), '');
            $description = $this->first_non_empty($attrs, array('description', 'summary', 'notes'), '');

            $published_at = $this->first_non_empty($attrs, array(
                'published_at','publish_at','released_at','release_date','created_at'
            ), '');

            $image_url = $this->first_non_empty($attrs, array(
                'image_url','artwork_url','thumbnail_url','cover_image_url','poster_url'
            ), '');

            $url = $this->first_non_empty($attrs, array(
                'public_url','share_url','url','web_url','video_url','audio_url'
            ), '');

            if ($public_only && $url === '' && $title !== '') {
                continue;
            }

            $out[] = array(
                'episode_id'   => $id,
                'title'        => $title,
                'description'  => $description,
                'image_url'    => $image_url,
                'url'          => $url,
                'published_at' => $published_at,
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

    private function pick_image_url($attrs) {
    if (!is_array($attrs)) return '';

    // 1) string fields
    $url = $this->first_non_empty($attrs, array(
        'image_url','photo_url','avatar_url','thumbnail_url',
        'cover_image_url','header_image_url','artwork_url','poster_url'
    ), '');
    if ($url !== '') return $url;

    // 2) nested objects (mis: avatar => ['url'=>...])
    $nested_keys = array('avatar','photo','image','artwork','cover_image','header_image');
    foreach ($nested_keys as $k) {
        if (isset($attrs[$k]) && is_array($attrs[$k])) {
            foreach (array('url','original','src','href','image_url','thumbnail_url') as $nk) {
                if (!empty($attrs[$k][$nk]) && is_string($attrs[$k][$nk])) {
                    return (string)$attrs[$k][$nk];
                }
            }
        }
    }

    return '';
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

        $enum_keys = array('visibility','church_center_visibility');
        foreach ($enum_keys as $k) {
            if (isset($event_attrs[$k]) && is_string($event_attrs[$k])) {
                $v = strtolower(trim($event_attrs[$k]));
                if (in_array($v, array('published','public','visible'), true)) return true;
                if (in_array($v, array('hidden','unpublished','private'), true)) return false;
            }
        }

        return true;
    }

    private function is_group_public($attrs) {
        if (!is_array($attrs) || empty($attrs)) {
            return true;
        }

        $bool_keys = array(
            'public',
            'is_public',
            'listed',
            'is_listed',
            'church_center_visible',
            'visible_in_church_center',
            'show_in_church_center',
        );
        foreach ($bool_keys as $k) {
            if (array_key_exists($k, $attrs)) {
                return (bool)$attrs[$k];
            }
        }

        $url = $this->first_non_empty($attrs, array(
            'public_church_center_web_url','public_church_center_url','church_center_web_url','church_center_url'
        ), '');
        if ($url !== '') {
            return true;
        }

        return true;
    }

    private function is_episode_public($attrs) {
        if (!is_array($attrs) || empty($attrs)) {
            return true;
        }

        $bool_keys = array('published','is_published','public','is_public');
        foreach ($bool_keys as $k) {
            if (array_key_exists($k, $attrs)) {
                return (bool)$attrs[$k];
            }
        }

        $date = $this->first_non_empty($attrs, array('published_at','publish_at','released_at','release_date'), '');
        if ($date !== '') {
            return true;
        }

        return true;
    }
}

endif;