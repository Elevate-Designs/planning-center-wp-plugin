<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events', array(__CLASS__, 'events_shortcode'));
        add_shortcode('pcc_calendar', array(__CLASS__, 'calendar_shortcode'));
    }

    public static function events_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'per_view'    => 3,
            'max'         => 6,
            'public_only' => 1,
        ), $atts, 'pcc_events');

        $plugin = pcc();
        if (!$plugin || !$plugin->data) {
            return '<div class="pcc pcc-empty">Plugin not initialized.</div>';
        }

        $items = $plugin->data->get_events_slider((int)$atts['max'], ((int)$atts['public_only'] === 1));

        if (empty($items)) {
            $msg = 'Events will appear here.';
            $err = $plugin->data->get_last_error();
            if (defined('WP_DEBUG') && WP_DEBUG && $err) {
                $msg .= ' <small style="display:block;opacity:.7;margin-top:6px;">' . esc_html($err) . '</small>';
            }
            return '<div class="pcc pcc-empty">' . $msg . '</div>';
        }

        ob_start();
        $template = PCC_PLUGIN_DIR . 'templates/events-list.php';
        if (file_exists($template)) {
            $atts_local = $atts;
            $items_local = $items;
            $atts = $atts_local;  // for template
            $items = $items_local;
            include $template;
        } else {
            echo '<div class="pcc pcc-empty">Template missing: templates/events-list.php</div>';
        }
        return ob_get_clean();
    }

    public static function calendar_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'months'      => 2,
            'public_only' => 1,
        ), $atts, 'pcc_calendar');

        $plugin = pcc();
        if (!$plugin || !$plugin->data) {
            return '<div class="pcc pcc-empty">Plugin not initialized.</div>';
        }

        $items = $plugin->data->get_calendar_months((int)$atts['months'], ((int)$atts['public_only'] === 1));

        if (empty($items)) {
            $msg = 'No events found for calendar.';
            $err = $plugin->data->get_last_error();
            if (defined('WP_DEBUG') && WP_DEBUG && $err) {
                $msg .= ' <small style="display:block;opacity:.7;margin-top:6px;">' . esc_html($err) . '</small>';
            }
            return '<div class="pcc pcc-empty">' . $msg . '</div>';
        }

        ob_start();
        $template = PCC_PLUGIN_DIR . 'templates/calendar-view.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="pcc pcc-empty">Template missing: templates/calendar-view.php</div>';
        }
        return ob_get_clean();
    }
}