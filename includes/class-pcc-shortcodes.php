<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events', [__CLASS__, 'events_shortcode']);
        add_shortcode('pcc_sermons', [__CLASS__, 'sermons_shortcode']);
        add_shortcode('pcc_groups', [__CLASS__, 'groups_shortcode']);
    }

    /* ======================================================
     * EVENTS
     * ====================================================== */
    public static function events_shortcode($atts) {

        // Divi / Builder preview safety
        if (isset($_GET['et_pb_preview'])) {
            return '<div class="pcc-preview">Events will appear here.</div>';
        }

        $atts = shortcode_atts([
            'limit'    => 10,
            'max'      => 6,
            'per_view' => 3,
        ], $atts, 'pcc_events');

        $limit = max(1, intval($atts['limit']));
        $max   = max(1, intval($atts['max']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '<div class="pcc-error">Plugin not initialized.</div>';
        }

        $json = $plugin->data->get_events(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_event_instances($json);

        if (empty($items)) {
            return '<div class="pcc-empty">No events available.</div>';
        }

        return self::render_template('events-list.php', [
            'items' => array_slice($items, 0, $max),
            'atts'  => $atts,
        ]);
    }

    /* ======================================================
     * SERMONS
     * ====================================================== */
    public static function sermons_shortcode($atts) {

        if (isset($_GET['et_pb_preview'])) {
            return '<div class="pcc-preview">Sermons will appear here.</div>';
        }

        $atts = shortcode_atts([
            'limit' => 10,
        ], $atts, 'pcc_sermons');

        $limit = max(1, intval($atts['limit']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '<div class="pcc-error">Plugin not initialized.</div>';
        }

        $json = $plugin->data->get_sermons(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        if (empty($items)) {
            return '<div class="pcc-empty">No sermons available.</div>';
        }

        return self::render_template('sermons-list.php', [
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ]);
    }

    /* ======================================================
     * GROUPS
     * ====================================================== */
    public static function groups_shortcode($atts) {

        if (isset($_GET['et_pb_preview'])) {
            return '<div class="pcc-preview">Groups will appear here.</div>';
        }

        $atts = shortcode_atts([
            'limit' => 20,
        ], $atts, 'pcc_groups');

        $limit = max(1, intval($atts['limit']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '<div class="pcc-error">Plugin not initialized.</div>';
        }

        $json = $plugin->data->get_groups(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        if (empty($items)) {
            return '<div class="pcc-empty">No groups available.</div>';
        }

        return self::render_template('groups-list.php', [
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ]);
    }

    /* ======================================================
     * HELPERS
     * ====================================================== */
    private static function render_error($err) {
        if (!is_wp_error($err)) {
            return '';
        }
        return '<div class="pcc-error">' . esc_html($err->get_error_message()) . '</div>';
    }

    private static function render_template($template_file, $vars = []) {
        $path = self::locate_template($template_file);
        if (!$path) {
            return '<div class="pcc-error">Template not found.</div>';
        }

        ob_start();
        extract($vars, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    private static function locate_template($template_file) {
        $theme_path = locate_template('planning-center/' . $template_file);
        if ($theme_path) {
            return $theme_path;
        }

        $plugin_path = PCC_PLUGIN_DIR . 'includes/templates/' . $template_file;
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return '';
    }

    /* ======================================================
     * NORMALIZERS
     * ====================================================== */
    private static function normalize_simple_resources($json) {
        $items = [];
        $data = $json['data'] ?? [];

        foreach ($data as $node) {
            $attrs = $node['attributes'] ?? [];
            $items[] = [
                'id'    => (string)($node['id'] ?? ''),
                'type'  => (string)($node['type'] ?? ''),
                'title' => self::guess_title($attrs),
                'url'   => self::guess_url($attrs),
                'date'  => self::guess_date($attrs),
                'raw'   => $node,
            ];
        }

        return $items;
    }

    private static function normalize_event_instances($json) {
        $items = [];
        $data = $json['data'] ?? [];
        $included_map = [];

        foreach ($json['included'] ?? [] as $inc) {
            if (isset($inc['type'], $inc['id'])) {
                $included_map[$inc['type'] . '/' . $inc['id']] = $inc;
            }
        }

        foreach ($data as $node) {
            $attrs = $node['attributes'] ?? [];

            $event = null;
            if (isset($node['relationships']['event']['data'])) {
                $rel = $node['relationships']['event']['data'];
                $key = $rel['type'] . '/' . $rel['id'];
                $event = $included_map[$key] ?? null;
            }

            $eattrs = $event['attributes'] ?? [];

            $items[] = [
                'id'        => (string)($node['id'] ?? ''),
                'type'      => (string)($node['type'] ?? ''),
                'title'     => self::guess_title($eattrs ?: $attrs),
                'url'       => self::guess_url($eattrs ?: $attrs),
                'starts_at' => (string)($attrs['starts_at'] ?? ''),
                'ends_at'   => (string)($attrs['ends_at'] ?? ''),
                'raw'       => $node,
            ];
        }

        return $items;
    }

    private static function guess_title($attrs) {
        foreach (['title', 'name', 'summary'] as $k) {
            if (!empty($attrs[$k])) return $attrs[$k];
        }
        return '';
    }

    private static function guess_url($attrs) {
        foreach (['public_url', 'url', 'church_center_url', 'public_church_center_url'] as $k) {
            if (!empty($attrs[$k])) return $attrs[$k];
        }
        return '';
    }

    private static function guess_date($attrs) {
        foreach (['published_at', 'starts_at', 'created_at'] as $k) {
            if (!empty($attrs[$k])) return $attrs[$k];
        }
        return '';
    }
}