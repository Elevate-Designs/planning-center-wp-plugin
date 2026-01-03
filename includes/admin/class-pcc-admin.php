<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Admin')):

final class PCC_Admin {

    public static function register_menu() {
        add_options_page(
            __('Planning Center', 'pcc'),
            __('Planning Center', 'pcc'),
            'manage_options',
            'pcc-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting(
            'pcc_settings_group',
            defined('PCC_OPTION_KEY') ? PCC_OPTION_KEY : 'pcc_settings',
            [__CLASS__, 'sanitize_settings']
        );

        add_settings_section(
            'pcc_section_auth',
            __('Personal Access Token (PAT)', 'pcc'),
            function () {
                echo '<p>' . esc_html__('Use a Planning Center Personal Access Token. Enter the Application ID and Secret below.', 'pcc') . '</p>';
            },
            'pcc-settings'
        );

        add_settings_field(
            'pcc_app_id',
            __('Application ID', 'pcc'),
            [__CLASS__, 'field_app_id'],
            'pcc-settings',
            'pcc_section_auth'
        );

        add_settings_field(
            'pcc_secret',
            __('Secret', 'pcc'),
            [__CLASS__, 'field_secret'],
            'pcc-settings',
            'pcc_section_auth'
        );
    }

    public static function sanitize_settings($input) {
        $out = [];

        $old = get_option(defined('PCC_OPTION_KEY') ? PCC_OPTION_KEY : 'pcc_settings', []);
        if (!is_array($old)) {
            $old = [];
        }

        $out['app_id'] = isset($input['app_id']) ? sanitize_text_field($input['app_id']) : '';

        // Keep old encrypted secret if user leaves blank.
        $raw_secret = isset($input['secret']) ? (string)$input['secret'] : '';
        if ($raw_secret !== '') {
            if (class_exists('PCC_Crypto')) {
                $out['secret_enc'] = PCC_Crypto::encrypt($raw_secret);
                // Optional: remove plain secret
                $out['secret'] = '';
            } else {
                $out['secret'] = sanitize_text_field($raw_secret);
            }
        } else {
            // Preserve existing secret values
            if (!empty($old['secret_enc'])) {
                $out['secret_enc'] = (string)$old['secret_enc'];
            }
            if (!empty($old['secret'])) {
                $out['secret'] = (string)$old['secret'];
            }
        }

        return $out;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle manual cache refresh
        if (isset($_GET['pcc_refreshed']) && $_GET['pcc_refreshed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared.', 'pcc') . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Planning Center Integration (PAT)', 'pcc') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('pcc_settings_group');
        do_settings_sections('pcc-settings');
        submit_button(__('Save Settings', 'pcc'));
        echo '</form>';

        echo '<hr />';

        // Connection test
        echo '<h2>' . esc_html__('Connection', 'pcc') . '</h2>';
        $plugin = function_exists('pcc') ? pcc() : null;

        if (!$plugin || !isset($plugin->api) || !$plugin->api->has_credentials()) {
            echo '<p><strong>' . esc_html__('Status:', 'pcc') . '</strong> <span style="color:#b32d2e">' . esc_html__('Not configured', 'pcc') . '</span></p>';
            echo '<p>' . esc_html__('Please save your Application ID and Secret first.', 'pcc') . '</p>';
        } else {
            $probe = $plugin->data->get_events(false, 1);
            if (is_wp_error($probe)) {
                echo '<p><strong>' . esc_html__('Status:', 'pcc') . '</strong> <span style="color:#b32d2e">' . esc_html__('Error', 'pcc') . '</span></p>';
                echo '<p>' . esc_html($probe->get_error_message()) . '</p>';
            } else {
                echo '<p><strong>' . esc_html__('Status:', 'pcc') . '</strong> <span style="color: #00a32a">' . esc_html__('Connected', 'pcc') . '</span></p>';
                $count = (!empty($probe['data']) && is_array($probe['data'])) ? count($probe['data']) : 0;
                echo '<p>' . sprintf(esc_html__('API reachable. Sample events returned: %d', 'pcc'), (int)$count) . '</p>';
            }
        }

        // Refresh cache button
        echo '<h2>' . esc_html__('Cache', 'pcc') . '</h2>';
        echo '<p>' . esc_html__('If you changed credentials or want to see new data immediately, clear the plugin cache.', 'pcc') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="pcc_refresh_cache" />';
        wp_nonce_field('pcc_refresh_cache');
        submit_button(__('Refresh Cache Now', 'pcc'), 'secondary');
        echo '</form>';

        echo '</div>';
    }

    public static function handle_refresh_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Forbidden', 'pcc'));
        }

        check_admin_referer('pcc_refresh_cache');

        $plugin = function_exists('pcc') ? pcc() : null;
        if ($plugin && isset($plugin->cache) && is_object($plugin->cache) && method_exists($plugin->cache, 'clear_all')) {
            $plugin->cache->clear_all();
        } elseif (function_exists('wp_cache_flush')) {
            // Fallback: may be too aggressive on shared caches, but prevents fatal.
            wp_cache_flush();
        }

        wp_safe_redirect(add_query_arg(['page' => 'pcc-settings', 'pcc_refreshed' => '1'], admin_url('options-general.php')));
        exit;
    }

    public static function field_app_id() {
        $settings = get_option(defined('PCC_OPTION_KEY') ? PCC_OPTION_KEY : 'pcc_settings', []);
        $val = is_array($settings) ? ($settings['app_id'] ?? '') : '';

        echo '<input type="text" name="' . esc_attr((defined('PCC_OPTION_KEY') ? PCC_OPTION_KEY : 'pcc_settings')) . '[app_id]" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('From Planning Center → Developer → Personal Access Tokens.', 'pcc') . '</p>';
    }

    public static function field_secret() {
        echo '<input type="password" name="' . esc_attr((defined('PCC_OPTION_KEY') ? PCC_OPTION_KEY : 'pcc_settings')) . '[secret]" value="" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">' . esc_html__('Leave blank to keep the existing secret.', 'pcc') . '</p>';
    }
}

endif;
