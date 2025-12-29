<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Cron {

    const HOOK = 'pcc_cron_warm_cache';

    /**
     * Add custom schedules.
     */
    public static function register_schedules() {
        add_filter('cron_schedules', array(__CLASS__, 'add_schedules'));

        // Ensure schedule is in place (handles cases where settings were changed).
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_init', array(__CLASS__, 'maybe_reschedule'));
        }
    }

    public static function add_schedules($schedules) {
        $schedules['pcc_15m'] = array(
            'interval' => 15 * 60,
            'display'  => __('Every 15 minutes (PCC)', 'pcc'),
        );
        return $schedules;
    }

    public static function maybe_reschedule() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $enabled  = isset($settings['cron_enabled']) ? (bool) $settings['cron_enabled'] : true;
        $recurrence = isset($settings['cron_recurrence']) ? (string) $settings['cron_recurrence'] : 'hourly';

        if (!$enabled) {
            self::deactivate();
            return;
        }

        $current = wp_get_schedule(self::HOOK);
        if ($current !== $recurrence) {
            self::deactivate();
            self::activate();
        }
    }

    public static function activate() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $enabled  = isset($settings['cron_enabled']) ? (bool) $settings['cron_enabled'] : true;
        if (!$enabled) {
            return;
        }

        $recurrence = isset($settings['cron_recurrence']) ? (string) $settings['cron_recurrence'] : 'hourly';

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, $recurrence, self::HOOK);
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
        wp_clear_scheduled_hook(self::HOOK);
    }

    public static function run() {
        if (!function_exists('pcc')) {
            return;
        }

        $plugin = pcc();

        if (!$plugin->api->has_credentials()) {
            return;
        }

        // Warm all caches.
        $plugin->data->get_events(true);
        $plugin->data->get_sermons(true);
        $plugin->data->get_groups(true);
    }
}
