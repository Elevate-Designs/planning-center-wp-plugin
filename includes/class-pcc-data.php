<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Data {

    /** @var PCC_API */
    private $api;

    /** @var PCC_Cache|null */
    private $cache;

    public function __construct(PCC_API $api, $cache = null) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    private function cache_get($key) {
        if ($this->cache && method_exists($this->cache, 'get')) return $this->cache->get($key);
        return false;
    }
    private function cache_set($key, $value, $ttl = 300) {
        if ($this->cache && method_exists($this->cache, 'set')) return $this->cache->set($key, $value, $ttl);
        return false;
    }

    /**
     * Slider data:
     * - default: upcoming 60 hari
     * - $public_only: kalau true, filter event yang punya public/church_center url
     * - auto fallback: kalau kosong, coba ulang tanpa public_only
     */
    public function get_events_slider($max = 6, $public_only = true) {
        $max = max(1, (int)$max);

        $now = current_time('timestamp');
        $start = gmdate('Y-m-d\T00:00:00\Z', $now);
        $end   = gmdate('Y-m-d\T23:59:59\Z', $now + 60 * DAY_IN_SECONDS);

        $items = $this->get_events_range($start, $end, 100, $public_only);

        if ($public_only && empty($items)) {
            // fallback biar tidak blank
            $items = $this->get_events_range($start, $end, 100, false);
        }

        return array_slice($items, 0, $max);
    }

    /**
     * Calendar data by range (ISO UTC strings)
     */
    public function get_events_range($starts_gte_iso, $starts_lte_iso, $per_page = 100, $public_only = true) {
        $cache_key = 'events_range_' . md5($starts_gte_iso . '|' . $starts_lte_iso . '|' . (int)$per_page . '|' . ($public_only ? '1' : '0'));

        $cached = $this->cache_get($cache_key);
        if (is_array($cached)) return $cached;

        $q = array(
            'per_page' => max(1, (int)$per_page),
            'include'  => 'event',
            'order'    => 'starts_at',
            // date range
            'where[starts_at][gte]' => $starts_gte_iso,
            'where[starts_at][lte]' => $starts_lte_iso,
        );

        $json = $this->api->get_event_instances($q);
        if (is_wp_error($json)) {
            $this->cache_set($cache_key, array(), 60);
            return array();
        }

        $data = $json['data'] ?? array();
        $included = $json['included'] ?? array();

        // Map included events by id
        $eventsById = array();
        foreach ($included as $inc) {
            if (!is_array($inc)) continue;
            if (($inc['type'] ?? '') !== 'Event') continue;
            $eid = (string)($inc['id'] ?? '');
            if ($eid === '') continue;
            $eventsById[$eid] = $inc;
        }

        $items = array();

        foreach ($data as $row) {
            if (!is_array($row)) continue;

            $attrs = $row['attributes'] ?? array();
            $rels  = $row['relationships'] ?? array();

            $starts_at = $attrs['starts_at'] ?? '';
            $ends_at   = $attrs['ends_at'] ?? '';

            // related event id
            $event_id = '';
            if (isset($rels['event']['data']['id'])) {
                $event_id = (string)$rels['event']['data']['id'];
            }

            $event = $eventsById[$event_id] ?? null;
            $eattr = is_array($event) ? ($event['attributes'] ?? array()) : array();

            $title = (string)($eattr['name'] ?? $eattr['title'] ?? '');
            if ($title === '') $title = (string)($attrs['name'] ?? '');

            // Public / Church Center URL (filter "Public times only")
            $url = (string)(
                $eattr['church_center_url']
                ?? $eattr['public_url']
                ?? $eattr['url']
                ?? $eattr['website_url']
                ?? ''
            );

            // Some APIs store booleans for Church Center visibility
            $cc_visible = null;
            foreach (array('visible_in_church_center', 'show_in_church_center', 'published_to_church_center') as $k) {
                if (array_key_exists($k, $eattr)) { $cc_visible = (bool)$eattr[$k]; break; }
            }

            if ($public_only) {
                // “Public times only”: paling aman = harus ada url publik ATAU flag visible true
                if ($url === '' && $cc_visible !== true) {
                    continue;
                }
            }

            // Description/summary
            $desc = (string)(
                $eattr['description']
                ?? $eattr['summary']
                ?? $eattr['details']
                ?? ''
            );

            // Event image: coba beberapa key umum
            $image = (string)(
                $eattr['image_url']
                ?? $eattr['image_thumbnail_url']
                ?? $eattr['banner_image_url']
                ?? $eattr['thumbnail_url']
                ?? ''
            );

            $location = (string)($eattr['location'] ?? $eattr['location_name'] ?? '');

            $items[] = array(
                'event_id'     => $event_id,
                'title'        => $title,
                'url'          => $url,
                'starts_at'    => $starts_at,
                'ends_at'      => $ends_at,
                'description'  => $desc,
                'location'     => $location,
                'image_url'    => $image,
            );
        }

        // Sort by starts_at
        usort($items, function($a, $b) {
            return strcmp((string)$a['starts_at'], (string)$b['starts_at']);
        });

        $this->cache_set($cache_key, $items, 300);
        return $items;
    }

    /**
     * Calendar month data: returns items for month + next months count
     */
    public function get_calendar_months($months = 2, $public_only = true) {
        $months = max(1, min(6, (int)$months));

        $tz = wp_timezone();
        $now = new DateTime('now', $tz);

        $start = (clone $now)->modify('first day of this month')->setTime(0,0,0);
        $end   = (clone $start)->modify('first day of +' . $months . ' month')->modify('-1 day')->setTime(23,59,59);

        // Convert to UTC ISO
        $start_utc = (clone $start)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T00:00:00\Z');
        $end_utc   = (clone $end)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T23:59:59\Z');

        $items = $this->get_events_range($start_utc, $end_utc, 200, $public_only);

        if ($public_only && empty($items)) {
            $items = $this->get_events_range($start_utc, $end_utc, 200, false);
        }

        return $items;
    }

    public function get_last_error() {
        return $this->api->get_last_error();
    }
}