<?php
if (!defined('ABSPATH')) { exit; }

class PCC_Admin {

  public static function register_menu() {
    add_options_page(
      __('Planning Center Integrator', 'pcc'),
      __('Planning Center', 'pcc'),
      'manage_options',
      'pcc-settings',
      array(__CLASS__, 'render_settings_page')
    );
  }

  public static function register_settings() {
    register_setting(
      'pcc_settings_group',
      PCC_OPTION_KEY,
      array(__CLASS__, 'sanitize_settings')
    );

    add_settings_section(
      'pcc_main_section',
      __('Credentials (PAT)', 'pcc'),
      function () {
        echo '<p>' . esc_html__('Use your Planning Center Personal Access Token (App ID + Secret).', 'pcc') . '</p>';
      },
      'pcc-settings'
    );

    add_settings_field(
      'pcc_app_id',
      __('App ID', 'pcc'),
      array(__CLASS__, 'field_app_id'),
      'pcc-settings',
      'pcc_main_section'
    );

    add_settings_field(
      'pcc_secret',
      __('Secret', 'pcc'),
      array(__CLASS__, 'field_secret'),
      'pcc-settings',
      'pcc_main_section'
    );
  }

  public static function sanitize_settings($input) {
    $existing = get_option(PCC_OPTION_KEY, array());

    $out = array();
    $out['app_id'] = isset($input['app_id']) ? sanitize_text_field($input['app_id']) : '';

    // Keep old secret if user leaves blank (so they don't have to retype)
    $new_secret = isset($input['secret']) ? sanitize_text_field($input['secret']) : '';
    if ($new_secret === '' && !empty($existing['secret'])) {
      $out['secret'] = $existing['secret'];
    } else {
      $out['secret'] = $new_secret;
    }

    // Cache bust integer
    $out['cache_bust'] = isset($existing['cache_bust']) ? (int)$existing['cache_bust'] : 1;
    if (isset($input['cache_bust'])) {
      $out['cache_bust'] = (int)$input['cache_bust'];
    }

    return $out;
  }

  public static function field_app_id() {
    $opt = get_option(PCC_OPTION_KEY, array());
    $val = isset($opt['app_id']) ? (string)$opt['app_id'] : '';
    echo '<input type="text" class="regular-text" name="' . esc_attr(PCC_OPTION_KEY) . '[app_id]" value="' . esc_attr($val) . '" />';
  }

  public static function field_secret() {
    echo '<input type="password" class="regular-text" name="' . esc_attr(PCC_OPTION_KEY) . '[secret]" value="" autocomplete="new-password" />';
    echo '<p class="description">' . esc_html__('Leave blank to keep existing secret.', 'pcc') . '</p>';
  }

  public static function render_settings_page() {
    if (!current_user_can('manage_options')) { return; }

    // Handle cache clear (cache bust)
    if (isset($_POST['pcc_clear_cache']) && check_admin_referer('pcc_clear_cache_action', 'pcc_clear_cache_nonce')) {
      $opt = get_option(PCC_OPTION_KEY, array());
      $opt['cache_bust'] = isset($opt['cache_bust']) ? ((int)$opt['cache_bust'] + 1) : 2;
      update_option(PCC_OPTION_KEY, $opt);
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared.', 'pcc') . '</p></div>';
    }

    // Connection test
    $status_html = '';
    if (class_exists('PCC_API')) {
      $api = new PCC_API();
      $test = $api->get('/calendar/v2/events', array('per_page' => 1));

      if (is_wp_error($test)) {
        $status_html = '<span style="color:#b32d2e;">' . esc_html__('Not connected:', 'pcc') . '</span> ' . esc_html($test->get_error_message());
      } else {
        $status_html = '<span style="color:#008a20;">' . esc_html__('Connected ✅', 'pcc') . '</span>';
      }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Planning Center Integrator', 'pcc') . '</h1>';
    if ($status_html) {
      echo '<p><strong>' . esc_html__('Status:', 'pcc') . '</strong> ' . $status_html . '</p>';
    }

    echo '<form method="post" action="options.php">';
    settings_fields('pcc_settings_group');
    do_settings_sections('pcc-settings');
    submit_button();
    echo '</form>';

    echo '<hr />';
    echo '<form method="post">';
    wp_nonce_field('pcc_clear_cache_action', 'pcc_clear_cache_nonce');
    echo '<p><button class="button" name="pcc_clear_cache" value="1" type="submit">' . esc_html__('Clear Cache', 'pcc') . '</button></p>';
    echo '</form>';

    echo '</div>';
  }
}