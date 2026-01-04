<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Shortcodes')):

final class PCC_Shortcodes {

    /** @var PCC_Shortcodes|null */
    private static $instance = null;

    /** @var PCC_Data */
    private $data;

    /**
     * Dipanggil oleh PCC_Plugin saat init.
     * Supaya tidak crash di PHP 8+ (karena callback static harus valid)
     */
    public static function register($data = null) {
        if (self::$instance) {
            return;
        }

        if ($data === null) {
            $plugin = function_exists('pcc') ? pcc() : null;
            $data = ($plugin && isset($plugin->data)) ? $plugin->data : null;
        }

        if (!$data || !is_object($data)) {
            return;
        }

        self::$instance = new self($data);
    }

    private function __construct($data) {
        $this->data = $data;

        // Backward compatible alias
        add_shortcode('pcc_events', array($this, 'events_slider_shortcode'));

        // Events
        add_shortcode('pcc_events_slider', array($this, 'events_slider_shortcode'));
        add_shortcode('pcc_events_calendar', array($this, 'events_calendar_shortcode'));

        // Groups + Sermons
        add_shortcode('pcc_groups', array($this, 'groups_shortcode'));
        add_shortcode('pcc_sermons', array($this, 'sermons_shortcode'));

        // AJAX for calendar month view
        add_action('wp_ajax_pcc_get_events_month', array($this, 'ajax_get_events_month'));
        add_action('wp_ajax_nopriv_pcc_get_events_month', array($this, 'ajax_get_events_month'));
    }

    private function render_error($message) {
        return '<p class="pcc-error">' . esc_html($message) . '</p>';
    }

    /**
     * Slider shortcode
     * Usage: [pcc_events_slider limit="12" per_view="3" months_ahead="2" public_only="1"]
     */
    public function events_slider_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'limit'        => 12,
            'per_view'     => 3,
            'months_ahead' => 2,
            'public_only'  => 1,
        ), $atts, 'pcc_events_slider');

        $limit        = max(1, min(50, (int)$atts['limit']));
        $per_view     = max(1, min(6, (int)$atts['per_view']));
        $months_ahead = max(1, min(24, (int)$atts['months_ahead']));
        $public_only  = !empty($atts['public_only']);

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $start = new DateTimeImmutable('now', $tz);
        $end   = $start->modify('+' . $months_ahead . ' months');

        if (!method_exists($this->data, 'get_event_instances_in_range')) {
            return $this->render_error('PCC_Data::get_event_instances_in_range() not found.');
        }

        $items = $this->data->get_event_instances_in_range($start, $end, $public_only);

        if (is_wp_error($items)) {
            return $this->render_error($items->get_error_message());
        }
        if (!is_array($items)) {
            $items = array();
        }

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        // Ensure assets are enqueued
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('pcc-events-slider');
        }

        ob_start();
        $template = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR . 'includes/templates/events-list.php' : '';
        if ($template && file_exists($template)) {
            $atts['per_view'] = $per_view;
            include $template;
        } else {
            echo $this->render_error('Template not found: events-list.php');
        }
        return ob_get_clean();
    }

    /**
     * Calendar UI shortcode
     * Usage: [pcc_events_calendar month="2026-01" public_only="1" show_search="1"]
     */
    public function events_calendar_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'month'       => '',
            'public_only' => 1,
            'show_search' => 1,
        ), $atts, 'pcc_events_calendar');

        $public_only = !empty($atts['public_only']);
        $show_search = !empty($atts['show_search']);

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        $year  = (int)$now->format('Y');
        $month = (int)$now->format('m');

        if (!empty($atts['month']) && preg_match('/^\d{4}\-\d{2}$/', $atts['month'])) {
            $tmpY = (int)substr($atts['month'], 0, 4);
            $tmpM = (int)substr($atts['month'], 5, 2);
            if ($tmpY >= 1970 && $tmpY <= 2100 && $tmpM >= 1 && $tmpM <= 12) {
                $year = $tmpY;
                $month = $tmpM;
            }
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('pcc-events-calendar');
        }
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('pcc-events-calendar');
        }

        ob_start();
        $template = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR . 'includes/templates/events-calendar.php' : '';
        if ($template && file_exists($template)) {
            $vars = array(
                'year'        => $year,
                'month'       => $month,
                'public_only' => $public_only,
                'show_search' => $show_search,
            );
            extract($vars, EXTR_SKIP);
            include $template;
        } else {
            echo $this->render_error('Template not found: events-calendar.php');
        }

        return ob_get_clean();
    }

    /**
     * Groups shortcode
     * Usage: [pcc_groups limit="50" public_only="1"]
     */
    public function groups_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'limit'       => 50,
            'public_only' => 1,
        ), $atts, 'pcc_groups');

        $limit = max(1, min(200, (int)$atts['limit']));
        $public_only = !empty($atts['public_only']);

        if (!method_exists($this->data, 'get_groups')) {
            return $this->render_error('PCC_Data::get_groups() not found. Add it in class-pcc-data.php');
        }

        $items = $this->data->get_groups($limit, $public_only);

        if (is_wp_error($items)) {
            return $this->render_error($items->get_error_message());
        }
        if (!is_array($items)) {
            $items = array();
        }

        ob_start();
        $template = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR . 'includes/templates/groups-list.php' : '';
        if ($template && file_exists($template)) {
            include $template;
        } else {
            echo $this->render_error('Template not found: groups-list.php');
        }
        return ob_get_clean();
    }

    /**
     * Sermons shortcode
     * Usage: [pcc_sermons limit="12" public_only="1"]
     */
    public function sermons_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'limit'       => 12,
            'public_only' => 1,
        ), $atts, 'pcc_sermons');

        $limit = max(1, min(200, (int)$atts['limit']));
        $public_only = !empty($atts['public_only']);

        if (!method_exists($this->data, 'get_sermons')) {
            return $this->render_error('PCC_Data::get_sermons() not found. Add it in class-pcc-data.php');
        }

        $items = $this->data->get_sermons($limit, $public_only);

        if (is_wp_error($items)) {
            return $this->render_error($items->get_error_message());
        }
        if (!is_array($items)) {
            $items = array();
        }

        ob_start();
        $template = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR . 'includes/templates/sermons-list.php' : '';
        if ($template && file_exists($template)) {
            include $template;
        } else {
            echo $this->render_error('Template not found: sermons-list.php');
        }
        return ob_get_clean();
    }

    /**
     * AJAX: return month instances
     */
    public function ajax_get_events_month() {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'pcc_calendar_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $year  = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : 0;
        $month = isset($_REQUEST['month']) ? (int)$_REQUEST['month'] : 0;
        $public_only = !empty($_REQUEST['public_only']);

        if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
            wp_send_json_error(array('message' => 'Invalid year/month'), 400);
        }

        if (!method_exists($this->data, 'get_event_instances_for_month')) {
            wp_send_json_error(array('message' => 'PCC_Data::get_event_instances_for_month() not found'), 500);
        }

        $items = $this->data->get_event_instances_for_month($year, $month, $public_only);

        if (is_wp_error($items)) {
            wp_send_json_error(array('message' => $items->get_error_message()), 500);
        }
        if (!is_array($items)) {
            $items = array();
        }

        wp_send_json_success(array(
            'year'  => $year,
            'month' => $month,
            'items' => $items,
        ));
    }
}

endif;