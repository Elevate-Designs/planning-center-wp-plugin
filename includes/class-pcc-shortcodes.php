<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events', array(__CLASS__, 'events_shortcode'));
        add_shortcode('pcc_sermons', array(__CLASS__, 'sermons_shortcode'));
        add_shortcode('pcc_groups', array(__CLASS__, 'groups_shortcode'));
    }

    /* =========================
     * EVENTS
     * ========================= */
    public static function events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit'    => 10,
            'max'      => 6,
            'per_view' => 3,
        ), $atts, 'pcc_events');

        $limit    = max(1, (int)$atts['limit']);
        $max      = max(1, (int)$atts['max']);
        $per_view = max(1, (int)$atts['per_view']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || empty($plugin->api) || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        // Prefer Data layer if available, otherwise call API directly
        if (!empty($plugin->data) && method_exists($plugin->data, 'get_events')) {
            $json = $plugin->data->get_events(false, $limit);
        } else {
            // direct call (safe fallback)
            $json = $plugin->api->get_json('/calendar/v2/event_instances', array(
                'per_page' => $limit,
                'include'  => 'event',
            ));
        }

        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_event_instances($json);
        $items = array_slice($items, 0, $max);

        if (empty($items)) {
            return '<div class="pcc-empty">Events will appear here.</div>';
        }

        return self::render_template('events-list.php', array(
            'items' => $items,
            'atts'  => array(
                'limit'    => $limit,
                'max'      => $max,
                'per_view' => $per_view,
            ),
        ));
    }

    /* =========================
     * SERMONS
     * ========================= */
    public static function sermons_shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 10), $atts, 'pcc_sermons');
        $limit = max(1, (int)$atts['limit']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || empty($plugin->api) || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        if (!empty($plugin->data) && method_exists($plugin->data, 'get_sermons')) {
            $json = $plugin->data->get_sermons(false, $limit);
        } else {
            $json = $plugin->api->get_json('/publishing/v2/episodes', array('per_page' => $limit));
        }

        if (is_wp_error($json)) return self::render_error($json);

        $items = self::normalize_simple_resources($json);
        return self::render_template('sermons-list.php', array(
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ));
    }

    /* =========================
     * GROUPS
     * ========================= */
    public static function groups_shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 20), $atts, 'pcc_groups');
        $limit = max(1, (int)$atts['limit']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || empty($plugin->api) || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        if (!empty($plugin->data) && method_exists($plugin->data, 'get_groups')) {
            $json = $plugin->data->get_groups(false, $limit);
        } else {
            $json = $plugin->api->get_json('/groups/v2/groups', array('per_page' => $limit));
        }

        if (is_wp_error($json)) return self::render_error($json);

        $items = self::normalize_simple_resources($json);
        return self::render_template('groups-list.php', array(
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ));
    }

    /* =========================
     * HELPERS
     * ========================= */
    private static function render_error($err) {
        if (!is_wp_error($err)) return '';
        return '<div class="pcc-error">' . esc_html($err->get_error_message()) . '</div>';
    }

    private static function render_template($template_file, $vars = array()) {
        $path = self::locate_template($template_file);
        if (!$path) return '';

        ob_start();
        extract($vars, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    private static function locate_template($template_file) {
        $theme_path = locate_template('planning-center/' . $template_file);
        if ($theme_path) return $theme_path;

        $plugin_path = PCC_PLUGIN_DIR . 'includes/templates/' . $template_file;
        return file_exists($plugin_path) ? $plugin_path : '';
    }

    /* =========================
     * NORMALIZERS
     * ========================= */
    private static function normalize_simple_resources($json) {
        $items = array();
        $data = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : array();

        foreach ($data as $node) {
            $attrs = (isset($node['attributes']) && is_array($node['attributes'])) ? $node['attributes'] : array();

            $items[] = array(
                'id'    => isset($node['id']) ? (string)$node['id'] : '',
                'title' => self::guess_title($attrs),
                'url'   => self::guess_url($attrs),
                'date'  => self::guess_date($attrs),
                'raw'   => $node,
            );
        }

        return $items;
    }

    private static function normalize_event_instances($json) {
        $items = array();
        $data = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : array();

        // Build included map
        $included_map = array();
        $included = (isset($json['included']) && is_array($json['included'])) ? $json['included'] : array();
        foreach ($included as $inc) {
            if (!isset($inc['type'], $inc['id'])) continue;
            $included_map[$inc['type'] . '/' . $inc['id']] = $inc;
        }

        foreach ($data as $node) {
            $attrs = (isset($node['attributes']) && is_array($node['attributes'])) ? $node['attributes'] : array();

            $event_title = '';
            $event_url   = '';

            // relationship: event
            $event = null;
            if (isset($node['relationships']['event']['data']['type'], $node['relationships']['event']['data']['id'])) {
                $rel = $node['relationships']['event']['data'];
                $key = $rel['type'] . '/' . $rel['id'];
                if (isset($included_map[$key])) $event = $included_map[$key];
            }

            if (is_array($event)) {
                $eattrs = (isset($event['attributes']) && is_array($event['attributes'])) ? $event['attributes'] : array();
                $event_title = self::guess_title($eattrs);
                $event_url   = self::guess_url($eattrs);
            }

            // Fallback kalau included kosong
            if ($event_title === '') $event_title = self::guess_title($attrs);
            if ($event_url === '')   $event_url   = self::guess_url($attrs);

            $items[] = array(
                'id'        => isset($node['id']) ? (string)$node['id'] : '',
                'title'     => $event_title,
                'url'       => $event_url,
                'starts_at' => isset($attrs['starts_at']) ? (string)$attrs['starts_at'] : '',
                'ends_at'   => isset($attrs['ends_at']) ? (string)$attrs['ends_at'] : '',
                'raw'       => $node,
                'event_raw' => $event,
            );
        }

        return $items;
    }

    private static function guess_title($attrs) {
        foreach (array('title', 'name', 'summary') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) return $attrs[$k];
        }
        return '';
    }

    private static function guess_url($attrs) {
        foreach (array('public_url', 'url', 'church_center_url', 'public_church_center_url') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) return $attrs[$k];
        }
        return '';
    }

    private static function guess_date($attrs) {
        foreach (array('published_at', 'starts_at', 'created_at', 'updated_at') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) return $attrs[$k];
        }
        return '';
    }
}