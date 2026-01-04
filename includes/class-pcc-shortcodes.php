<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Shortcodes')):

final class PCC_Shortcodes {

    public static function register() {
        // Events (slider) - backward compatible
        add_shortcode('pcc_events', [__CLASS__, 'events_slider_shortcode']);
        add_shortcode('pcc_events_slider', [__CLASS__, 'events_slider_shortcode']);

        // Calendar
        add_shortcode('pcc_events_calendar', [__CLASS__, 'events_calendar_shortcode']);

        // Existing
        add_shortcode('pcc_sermons', [__CLASS__, 'sermons_shortcode']);
        add_shortcode('pcc_groups', [__CLASS__, 'groups_shortcode']);

        // Backward compatibility aliases
        add_shortcode('planning_center_events', [__CLASS__, 'events_slider_shortcode']);
        add_shortcode('planning_center_sermons', [__CLASS__, 'sermons_shortcode']);
        add_shortcode('planning_center_groups', [__CLASS__, 'groups_shortcode']);
        add_shortcode('planning_center_events_calendar', [__CLASS__, 'events_calendar_shortcode']);

        // AJAX (calendar)
        add_action('wp_ajax_pcc_get_events_month', [__CLASS__, 'ajax_get_events_month']);
        add_action('wp_ajax_nopriv_pcc_get_events_month', [__CLASS__, 'ajax_get_events_month']);
    }

    /* ======================================================
     * EVENTS SLIDER
     * ====================================================== */
    public static function events_slider_shortcode($atts = []) {
        $atts = shortcode_atts([
            'limit'        => 12,
            'per_view'     => 3,
            'months_ahead' => 2,
            'public_only'  => 1,

            // legacy alias (kalau ada yang pakai max)
            'max'          => '',
        ], $atts, 'pcc_events_slider');

        $limit = (int) $atts['limit'];
        if (!empty($atts['max'])) {
            $limit = (int) $atts['max'];
        }
        $limit = max(1, min(50, $limit));

        $per_view     = max(1, min(6, (int) $atts['per_view']));
        $months_ahead = max(1, min(24, (int) $atts['months_ahead']));
        $public_only  = !empty($atts['public_only']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !isset($plugin->api) || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set. Please go to <strong>Settings → Planning Center</strong> and fill your Personal Access Token.</div>';
        }

        if (!isset($plugin->data) || !method_exists($plugin->data, 'get_event_instances_in_range')) {
            return '<div class="pcc-error">Plugin data service is not ready (missing range loader).</div>';
        }

        // Range: now -> now + months_ahead
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $start = new DateTimeImmutable('now', $tz);
        $end   = $start->modify('+' . $months_ahead . ' months');

        $items = $plugin->data->get_event_instances_in_range($start, $end, $public_only);
        if (is_wp_error($items)) {
            return self::render_error($items);
        }

        if (empty($items)) {
            return '<div class="pcc-empty">Events will appear here.</div>';
        }

        $items = array_slice($items, 0, $limit);

        // Ensure slider JS is loaded
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('pcc-events-slider');
        }

        return self::render_template('events-list.php', [
            'items' => $items,
            'atts'  => [
                'per_view' => $per_view,
            ],
        ]);
    }

    /* ======================================================
     * EVENTS CALENDAR
     * ====================================================== */
    public static function events_calendar_shortcode($atts = []) {
        $atts = shortcode_atts([
            'month'       => '', // "YYYY-MM"
            'public_only' => 1,
            'show_search' => 1,
        ], $atts, 'pcc_events_calendar');

        $public_only = !empty($atts['public_only']);
        $show_search = !empty($atts['show_search']);

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year  = (int) $now->format('Y');
        $month = (int) $now->format('m');

        if (!empty($atts['month']) && preg_match('/^\d{4}\-\d{2}$/', $atts['month'])) {
            $tmpY = (int) substr($atts['month'], 0, 4);
            $tmpM = (int) substr($atts['month'], 5, 2);
            if ($tmpY >= 1970 && $tmpY <= 2100 && $tmpM >= 1 && $tmpM <= 12) {
                $year = $tmpY;
                $month = $tmpM;
            }
        }

        // Enqueue assets
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('pcc-events-calendar');
        }
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('pcc-events-calendar');
        }

        return self::render_template('events-calendar.php', [
            'year'        => $year,
            'month'       => $month,
            'public_only' => $public_only,
            'show_search' => $show_search,
        ]);
    }

    public static function ajax_get_events_month() {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'pcc_calendar_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $year  = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : 0;
        $month = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : 0;
        $public_only = !empty($_REQUEST['public_only']);

        if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
            wp_send_json_error(['message' => 'Invalid year/month'], 400);
        }

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !isset($plugin->data) || !method_exists($plugin->data, 'get_event_instances_for_month')) {
            wp_send_json_error(['message' => 'Plugin not ready'], 500);
        }

        $items = $plugin->data->get_event_instances_for_month($year, $month, $public_only);
        if (is_wp_error($items)) {
            wp_send_json_error(['message' => $items->get_error_message()], 500);
        }

        wp_send_json_success([
            'year'  => $year,
            'month' => $month,
            'items' => $items,
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
        if (!$plugin || !isset($plugin->api) || !$plugin->api->has_credentials()) {
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
        if (!$plugin || !isset($plugin->api) || !$plugin->api->has_credentials()) {
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
        return (string) ob_get_clean();
    }

    private static function locate_template($template_file) {
        $theme_path = locate_template('planning-center/' . $template_file);
        if ($theme_path) {
            return $theme_path;
        }

        $plugin_path = (defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR : plugin_dir_path(__FILE__) . '../') . 'includes/templates/' . $template_file;
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return '';
    }

    private static function normalize_simple_resources($json) {
        $items = [];
        if (empty($json['data']) || !is_array($json['data'])) {
            return $items;
        }

        foreach ($json['data'] as $row) {
            $attrs = isset($row['attributes']) && is_array($row['attributes']) ? $row['attributes'] : [];
            $items[] = [
                'title' => $attrs['title'] ?? ($attrs['name'] ?? ''),
                'url'   => $attrs['public_url'] ?? ($attrs['url'] ?? ''),
            ];
        }

        return $items;
    }
}

endif;