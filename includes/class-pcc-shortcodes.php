<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Shortcodes')):

final class PCC_Shortcodes {

    /** @var PCC_Data */
    private $data;

    public function __construct($data) {
        $this->data = $data;

        // Backward compatible
        add_shortcode('pcc_events', array($this, 'events_slider_shortcode'));

        // New
        add_shortcode('pcc_events_slider', array($this, 'events_slider_shortcode'));
        add_shortcode('pcc_events_calendar', array($this, 'events_calendar_shortcode'));

        // AJAX for calendar month view
        add_action('wp_ajax_pcc_get_events_month', array($this, 'ajax_get_events_month'));
        add_action('wp_ajax_nopriv_pcc_get_events_month', array($this, 'ajax_get_events_month'));
    }

    /**
     * Slider shortcode
     * Usage: [pcc_events_slider limit="12" per_view="3" months_ahead="2" public_only="1"]
     * (Also works with [pcc_events ...])
     */
    public function events_slider_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'limit'       => 12,
            'per_view'    => 3,
            'months_ahead'=> 2,
            'public_only' => 1,
        ), $atts, 'pcc_events_slider');

        $limit        = max(1, min(50, (int)$atts['limit']));
        $per_view     = max(1, min(6, (int)$atts['per_view']));
        $months_ahead = max(1, min(24, (int)$atts['months_ahead']));
        $public_only  = !empty($atts['public_only']);

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $start = new DateTimeImmutable('now', $tz);
        $end   = $start->modify('+' . $months_ahead . ' months');

        $items = $this->data->get_event_instances_in_range($start, $end, $public_only);
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
            echo '<p>' . esc_html__('Template not found: events-list.php', 'pcc') . '</p>';
        }
        return ob_get_clean();
    }

    /**
     * Calendar UI shortcode
     * Usage: [pcc_events_calendar month="2026-01" public_only="1" show_search="1"]
     */
    public function events_calendar_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'month'       => '',   // "YYYY-MM" (optional)
            'public_only' => 1,
            'show_search' => 1,
        ), $atts, 'pcc_events_calendar');

        $public_only = !empty($atts['public_only']);
        $show_search = !empty($atts['show_search']);

        // Determine initial month
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

        // Enqueue calendar assets (registered by PCC_Plugin)
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
            // Make variables available to template
            extract($vars, EXTR_SKIP);
            include $template;
        } else {
            echo '<p>' . esc_html__('Template not found: events-calendar.php', 'pcc') . '</p>';
        }

        return ob_get_clean();
    }

    /**
     * AJAX: return month instances
     * POST: action=pcc_get_events_month&nonce=...&year=2026&month=1&public_only=1
     */
    public function ajax_get_events_month() {
        // Nonce check
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

        $items = $this->data->get_event_instances_for_month($year, $month, $public_only);

        wp_send_json_success(array(
            'year'  => $year,
            'month' => $month,
            'items' => $items,
        ));
    }
}

endif;