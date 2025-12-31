<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Admin {

    const PAGE_SLUG = 'pcc-settings';
    const GROUP     = 'pcc_settings_group';

    public static function register_menu() {
        add_options_page(
            'Planning Center Integration (PAT)',
            'Planning Center',
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    public static function register_settings() {
        register_setting(self::GROUP, PCC_OPTION_KEY, array(__CLASS__, 'sanitize_settings'));

        add_settings_section(
            'pcc_section_main',
            'Personal Access Token (PAT)',
            array(__CLASS__, 'section_main_desc'),
            self::PAGE_SLUG
        );

        add_settings_field(
            'pcc_app_id',
            'Application ID',
            array(__CLASS__, 'field_app_id'),
            self::PAGE_SLUG,
            'pcc_section_main'
        );

        add_settings_field(
            'pcc_secret',
            'Secret',
            array(__CLASS__, 'field_secret'),
            self::PAGE_SLUG,
            'pcc_section_main'
        );
    }

    private static function get_settings() {
        $s = get_option(PCC_OPTION_KEY, array());
        return is_array($s) ? $s : array();
    }

    public static function section_main_desc() {
        echo '<p>Masukkan <strong>Application ID</strong> dan <strong>Secret</strong> dari Planning Center (Personal Access Token). Ini bukan OAuth.</p>';
    }

    public static function field_app_id() {
        $s = self::get_settings();
        $val = '';

        // prefer PAT key
        if (!empty($s['app_id'])) $val = (string)$s['app_id'];

        // compatibility: kalau dulu keburu pakai oauth keys
        if ($val === '' && !empty($s['oauth_client_id'])) $val = (string)$s['oauth_client_id'];

        echo '<input type="text" name="' . esc_attr(PCC_OPTION_KEY) . '[app_id]" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Contoh: 123456 atau string dari Planning Center PAT</p>';
    }

    public static function field_secret() {
        echo '<input type="password" name="' . esc_attr(PCC_OPTION_KEY) . '[secret]" value="" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">Kosongkan jika tidak ingin mengubah secret yang sudah tersimpan.</p>';
    }

    public static function sanitize_settings($input) {
        $old = self::get_settings();
        $out = $old;

        if (!is_array($input)) $input = array();

        // app_id
        $app_id = isset($input['app_id']) ? trim((string)$input['app_id']) : '';
        if ($app_id !== '') {
            $out['app_id'] = $app_id;
        }

        // secret (only update if user typed)
        $secret = isset($input['secret']) ? trim((string)$input['secret']) : '';
        if ($secret !== '') {
            if (class_exists('PCC_Crypto')) {
                $out['secret_enc'] = PCC_Crypto::encrypt($secret);
                unset($out['secret']); // remove plain if exists
            } else {
                // fallback if no crypto file exists
                $out['secret'] = $secret;
                unset($out['secret_enc']);
            }
        }

        // cleanup oauth keys so UI doesn't confuse
        unset($out['oauth_client_id'], $out['oauth_client_secret'], $out['oauth_client_secret_enc'], $out['client_id'], $out['client_secret']);

        return $out;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Planning Center Integration (PAT)</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        do_settings_sections(self::PAGE_SLUG);
        submit_button('Save Settings');
        echo '</form>';

        echo '<hr />';
        self::render_connection_test();

        echo '</div>';
    }

    private static function render_connection_test() {
        echo '<h2>Connection Test</h2>';

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || empty($plugin->api) || !method_exists($plugin->api, 'has_credentials')) {
            echo '<div class="notice notice-error"><p>Plugin API belum siap.</p></div>';
            return;
        }

        if (!$plugin->api->has_credentials()) {
            echo '<div class="notice notice-warning"><p>Planning Center credentials are not set yet.</p></div>';
            return;
        }

        // Test simple call
        $res = $plugin->api->get_json('/calendar/v2/event_instances', array(
            'per_page' => 1,
            'include'  => 'event',
        ));

        if (is_wp_error($res)) {
            echo '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>✅ Connected! API request successful.</p></div>';
    }
}