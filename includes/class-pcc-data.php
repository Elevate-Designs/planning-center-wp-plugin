<?php
if (!defined('ABSPATH')) { exit; }

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
     * Fetch upcoming event instances (public times only) + include=event to get image.
     * Returns normalized array: each item has:
     *  - title, url, starts_at, ends_at, location, image_url, description
     */
    public function get_events($force = false, $limit = 50) {
        $limit = max(1, (int)$limit);

        $cache_key = 'events_v2_' . $limit;
        if (!$force && $this->cache) {
            $cached = $this->cache->get($cache_key);
            if (is_array($cached)) return $cached;
        }

        // Pull future instances and include event (for image/desc/location)
        // NOTE: Planning Center Calendar API supports include=event
        $params = array(
            'per_page' => $limit,
            'include'  => 'event',
            'order'    => 'starts_at',
            'filter'   => 'future',
        );

        $json = $this->api->get_json('/calendar/v2/event_instances', $params);
        if (is_wp_error($json)) return $json;

        $items = $this->normalize_event_instances_with_event($json);

        // "Public times only" -> keep only instances that have public_url
        $items = array_values(array_filter($items, function($it) {
            return !empty($it['url']);
        }));

        if ($this->cache) {
            // cache 10 minutes (adjust if needed)
            $this->cache->set($cache_key, $items, 10 * MINUTE_IN_SECONDS);
        }

        return $items;
    }

    private function normalize_event_instances_with_event($json) {
        $out = array();

        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : array();
        $included = isset($json['included']) && is_array($json['included']) ? $json['included'] : array();

        // Map included event by id
        $event_map = array();
        foreach ($included as $inc) {
            if (!is_array($inc)) continue;
            if (($inc['type'] ?? '') !== 'Event') continue;
            $eid = (string)($inc['id'] ?? '');
            if ($eid === '') continue;

            $attrs = isset($inc['attributes']) && is_array($inc['attributes']) ? $inc['attributes'] : array();

            $event_map[$eid] = array(
                'title'       => (string)($attrs['name'] ?? $attrs['summary'] ?? ''),
                'description' => (string)($attrs['description'] ?? $attrs['summary'] ?? ''),
                'location'    => (string)($attrs['location'] ?? $attrs['location_name'] ?? ''),
                'image_url'   => $this->extract_event_image_url($attrs),
            );
        }

        foreach ($data as $node) {
            if (!is_array($node)) continue;

            $attrs = isset($node['attributes']) && is_array($node['attributes']) ? $node['attributes'] : array();

            // Relationship -> event id
            $event_id = '';
            if (!empty($node['relationships']['event']['data']['id'])) {
                $event_id = (string)$node['relationships']['event']['data']['id'];
            }

            $event_meta = $event_id && isset($event_map[$event_id]) ? $event_map[$event_id] : array();

            $title = (string)($attrs['summary'] ?? $event_meta['title'] ?? '');
            $url   = (string)($attrs['public_url'] ?? '');
            $starts= (string)($attrs['starts_at'] ?? '');
            $ends  = (string)($attrs['ends_at'] ?? '');

            $location = (string)($attrs['location'] ?? $attrs['location_name'] ?? ($event_meta['location'] ?? ''));
            $desc     = (string)($event_meta['description'] ?? '');

            $img = (string)($event_meta['image_url'] ?? '');
            if ($img === '') {
                $img = $this->placeholder_image_data_uri();
            }

            $out[] = array(
                'id'          => (string)($node['id'] ?? ''),
                'event_id'    => $event_id,
                'title'       => $title,
                'url'         => $url,
                'starts_at'   => $starts,
                'ends_at'     => $ends,
                'location'    => $location,
                'description' => $desc,
                'image_url'   => $img,
            );
        }

        return $out;
    }

    /**
     * Try a bunch of possible attribute keys used by Planning Center for event images.
     * If none found, return empty string.
     */
    private function extract_event_image_url($attrs) {
        if (!is_array($attrs)) return '';

        $candidates = array(
            'image_url',
            'image_thumbnail_url',
            'image_thumb_url',
            'image_medium_url',
            'photo_url',
            'banner_url',
            'cover_image_url',
        );

        foreach ($candidates as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) {
                return trim($attrs[$k]);
            }
        }

        // Sometimes it's nested
        if (!empty($attrs['image']) && is_array($attrs['image'])) {
            foreach (array('url','large','medium','small','thumbnail') as $k) {
                if (!empty($attrs['image'][$k]) && is_string($attrs['image'][$k])) {
                    return trim($attrs['image'][$k]);
                }
            }
        }

        return '';
    }

    /**
     * Inline SVG placeholder (no extra file needed).
     */
    private function placeholder_image_data_uri() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450">
            <defs>
              <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
                <stop offset="0" stop-color="#0f172a"/>
                <stop offset="1" stop-color="#334155"/>
              </linearGradient>
            </defs>
            <rect width="800" height="450" fill="url(#g)"/>
            <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
              font-family="Arial, sans-serif" font-size="34" fill="#e2e8f0">Event</text>
          </svg>';

        $svg = rawurlencode($svg);
        return 'data:image/svg+xml;utf8,' . $svg;
    }
}