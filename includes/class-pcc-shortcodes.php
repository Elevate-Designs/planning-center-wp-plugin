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

        $atts = shortcode_atts([
            'limit'    => 10,
            'max'      => 6,
            'per_view' => 3,
        ], $atts, 'pcc_events');

        $limit    = max(1, (int) $atts['limit']);
        $max      = max(1, (int) $atts['max']);
        $per_view = max(1, (int) $atts['per_view']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        $json = $plugin->data->get_events(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_event_instances($json);

        if (empty($items)) {
            return '<div class="pcc-empty">Events will appear here.</div>';
        }

        $items = array_slice($items, 0, $max);

        return self::render_template('events-list.php', [
            'items' => $items,
            'atts'  => [
                'per_view' => $per_view,
                'max'      => $max,
            ],
        ]);
    }

    /* ======================================================
     * SERMONS
     * ====================================================== */
    public static function sermons_shortcode($atts) {

        $atts = shortcode_atts([
            'limit' => 10,
        ], $atts, 'pcc_sermons');

        $limit = max(1, (int) $atts['limit']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        $json = $plugin->data->get_sermons(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        return self::render_template('sermons-list.php', [
            'items' => array_slice($items, 0, $limit),
        ]);
    }

    /* ======================================================
     * GROUPS
     * ====================================================== */
    public static function groups_shortcode($atts) {

        $atts = shortcode_atts([
            'limit' => 20,
        ], $atts, 'pcc_groups');

        $limit = max(1, (int) $atts['limit']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        $json = $plugin->data->get_groups(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        return self::render_template('groups-list.php', [
            'items' => array_slice($items, 0, $limit),
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
            return '';
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

        if (empty($json['data']) || !is_array($json['data'])) {
            return $items;
        }

        foreach ($json['data'] as $node) {
            $attrs = $node['attributes'] ?? [];

            $items[] = [
                'id'    => $node['id'] ?? '',
                'title' => self::guess_title($attrs),
                'url'   => self::guess_url($attrs),
                'date'  => self::guess_date($attrs),
            ];
        }

        return $items;
    }

    private static function normalize_event_instances($json) {
        $items = [];

        if (empty($json['data']) || !is_array($json['data'])) {
            return $items;
        }

        foreach ($json['data'] as $node) {
            $attrs = $node['attributes'] ?? [];

            $items[] = [
                'id'        => $node['id'] ?? '',
                'title'     => $attrs['summary'] ?? '',
                'url'       => $attrs['public_url'] ?? '',
                'starts_at' => $attrs['starts_at'] ?? '',
                'ends_at'   => $attrs['ends_at'] ?? '',
            ];
        }

        return $items;
    }

    private static function guess_title($attrs) {
        return $attrs['title'] ?? $attrs['name'] ?? '';
    }

    private static function guess_url($attrs) {
        return $attrs['public_url'] ?? $attrs['url'] ?? '';
    }

    private static function guess_date($attrs) {
        return $attrs['published_at'] ?? $attrs['starts_at'] ?? '';
    }
}
