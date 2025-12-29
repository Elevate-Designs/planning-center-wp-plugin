<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Admin {

    const CAPABILITY = 'manage_options';

    public static function register_menu() {
        add_options_page(
            __('Planning Center', 'pcc'),
            __('Planning Center', 'pcc'),
            self::CAPABILITY,
            'pcc-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function register_settings() {
        register_setting('pcc_settings_group', PCC_OPTION_KEY, array(__CLASS__, 'sanitize_settings'));

        add_settings_section(
            'pcc_section_main',
            __('API Settings', 'pcc'),
            array(__CLASS__, 'render_section_main'),
            'pcc-settings'
        );

        add_settings_field(
            'pcc_app_id',
            __('Application ID', 'pcc'),
            array(__CLASS__, 'render_field_app_id'),
            'pcc-settings',
            'pcc_section_main'
        );

        add_settings_field(
            'pcc_secret',
            __('Secret', 'pcc'),
            array(__CLASS__, 'render_field_secret'),
            'pcc-settings',
            'pcc_section_main'
        );

        add_settings_section(
            'pcc_section_cache',
            __('Cache & Sync', 'pcc'),
            array(__CLASS__, 'render_section_cache'),
            'pcc-settings'
        );

        add_settings_field(
            'pcc_cache_ttl',
            __('Cache TTL (seconds)', 'pcc'),
            array(__CLASS__, 'render_field_cache_ttl'),
            'pcc-settings',
            'pcc_section_cache'
        );

        add_settings_field(
            'pcc_cron_enabled',
            __('Warm cache automatically (WP-Cron)', 'pcc'),
            array(__CLASS__, 'render_field_cron_enabled'),
            'pcc-settings',
            'pcc_section_cache'
        );

        add_settings_field(
            'pcc_cron_recurrence',
            __('Cron schedule', 'pcc'),
            array(__CLASS__, 'render_field_cron_recurrence'),
            'pcc-settings',
            'pcc_section_cache'
        );

        // Admin actions.
        add_action('admin_post_pcc_test_connection', array(__CLASS__, 'handle_test_connection'));
        add_action('admin_post_pcc_clear_cache', array(__CLASS__, 'handle_clear_cache'));
        add_action('admin_post_pcc_warm_cache', array(__CLASS__, 'handle_warm_cache'));
    }

    public static function sanitize_settings($input) {
        $existing = get_option(PCC_OPTION_KEY, array());
        $out = is_array($existing) ? $existing : array();

        $out['app_id'] = isset($input['app_id']) ? sanitize_text_field($input['app_id']) : '';

        // Only overwrite secret if user typed something.
        if (isset($input['secret']) && trim((string) $input['secret']) !== '') {
            $out['secret_enc'] = PCC_Crypto::encrypt(sanitize_text_field($input['secret']));
        } elseif (!isset($out['secret_enc'])) {
            $out['secret_enc'] = '';
        }

        $out['cache_ttl'] = isset($input['cache_ttl']) ? max(60, intval($input['cache_ttl'])) : 900;

        $out['cron_enabled'] = !empty($input['cron_enabled']) ? true : false;

        $allowed = array('pcc_15m', 'hourly', 'twicedaily', 'daily');
        $rec = isset($input['cron_recurrence']) ? (string) $input['cron_recurrence'] : 'hourly';
        $out['cron_recurrence'] = in_array($rec, $allowed, true) ? $rec : 'hourly';

        return $out;
    }

    public static function render_settings_page() {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $settings = get_option(PCC_OPTION_KEY, array());
        $has_secret = !empty($settings['secret_enc']);

        include PCC_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    public static function render_section_main() {
        echo '<p>' . esc_html__('Use a Planning Center Personal Access Token (Application ID + Secret).', 'pcc') . '</p>';
    }

    public static function render_section_cache() {
        echo '<p>' . esc_html__('We cache API responses in WordPress transients. WP-Cron can warm the cache in the background.', 'pcc') . '</p>';
    }

    public static function render_field_app_id() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $val = isset($settings['app_id']) ? $settings['app_id'] : '';
        echo '<input type="text" name="' . esc_attr(PCC_OPTION_KEY) . '[app_id]" value="' . esc_attr($val) . '" class="regular-text" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function render_field_secret() {
        // never prefill.
        echo '<input type="password" name="' . esc_attr(PCC_OPTION_KEY) . '[secret]" value="" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">' . esc_html__('Leave blank to keep the existing secret.', 'pcc') . '</p>';
    }

    public static function render_field_cache_ttl() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $val = isset($settings['cache_ttl']) ? intval($settings['cache_ttl']) : 900;
        echo '<input type="number" min="60" step="1" name="' . esc_attr(PCC_OPTION_KEY) . '[cache_ttl]" value="' . esc_attr($val) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function render_field_cron_enabled() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $enabled = isset($settings['cron_enabled']) ? (bool) $settings['cron_enabled'] : true;
        echo '<label><input type="checkbox" name="' . esc_attr(PCC_OPTION_KEY) . '[cron_enabled]" value="1" ' . checked(true, $enabled, false) . ' /> ' . esc_html__('Enable automatic cache warmup', 'pcc') . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function render_field_cron_recurrence() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $rec = isset($settings['cron_recurrence']) ? (string) $settings['cron_recurrence'] : 'hourly';
        $options = array(
            'pcc_15m'     => __('Every 15 minutes', 'pcc'),
            'hourly'      => __('Hourly', 'pcc'),
            'twicedaily'  => __('Twice daily', 'pcc'),
            'daily'       => __('Daily', 'pcc'),
        );

        echo '<select name="' . esc_attr(PCC_OPTION_KEY) . '[cron_recurrence]">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        foreach ($options as $k => $label) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($rec, $k, false) . '>' . esc_html($label) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function handle_test_connection() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('Not allowed.', 'pcc'));
        }
        check_admin_referer('pcc_test_connection');

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            wp_die(__('Plugin not initialized.', 'pcc'));
        }

        $results = array();

        $endpoints = array(
            'calendar'   => '/calendar/v2/events',
            'groups'     => '/groups/v2/groups',
            'publishing' => '/publishing/v2/episodes',
        );

        foreach ($endpoints as $key => $path) {
            $json = $plugin->api->get_json($path, array('per_page' => 1), false);
            $results[$key] = is_wp_error($json) ? $json->get_error_message() : 'OK';
        }

        set_transient('pcc_admin_test_results', $results, 60);

        wp_safe_redirect(add_query_arg(array('page' => 'pcc-settings', 'pcc_notice' => 'tested'), admin_url('options-general.php')));
        exit;
    }

    public static function handle_clear_cache() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('Not allowed.', 'pcc'));
        }
        check_admin_referer('pcc_clear_cache');

        $plugin = function_exists('pcc') ? pcc() : null;
        if ($plugin) {
            $plugin->cache->clear_all();
        }

        wp_safe_redirect(add_query_arg(array('page' => 'pcc-settings', 'pcc_notice' => 'cache_cleared'), admin_url('options-general.php')));
        exit;
    }

    public static function handle_warm_cache() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('Not allowed.', 'pcc'));
        }
        check_admin_referer('pcc_warm_cache');

        $plugin = function_exists('pcc') ? pcc() : null;
        if ($plugin) {
            $plugin->data->get_events(true);
            $plugin->data->get_sermons(true);
            $plugin->data->get_groups(true);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'pcc-settings', 'pcc_notice' => 'cache_warmed'), admin_url('options-general.php')));
        exit;
    }
}
