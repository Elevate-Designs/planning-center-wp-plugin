<?php
if (!defined('ABSPATH')) exit;

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events', [__CLASS__, 'events']);
    }

    public static function events() {
        $plugin = pcc();

        if (!$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center not connected</div>';
        }

        $json = $plugin->api->get_json('/calendar/v2/event_instances');

        if (empty($json['data'])) {
            return '<div class="pcc-empty">Events will appear here.</div>';
        }

        ob_start();
        foreach ($json['data'] as $e) {
            echo '<p>' . esc_html($e['attributes']['summary'] ?? 'Event') . '</p>';
        }
        return ob_get_clean();
    }
}