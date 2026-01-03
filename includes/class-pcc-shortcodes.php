<?php
if (!defined('ABSPATH')) { exit; }

class PCC_Shortcodes {

  public static function register() {
    add_shortcode('pcc_events', array(__CLASS__, 'events_slider'));
    add_shortcode('pcc_calendar', array(__CLASS__, 'calendar_view'));
  }

  public static function events_slider($atts) {
    $atts = shortcode_atts(array(
      'per_view' => 3,
      'max' => 6,
      'public_only' => 0,
    ), $atts, 'pcc_events');

    if (!function_exists('pcc') || !pcc() || empty(pcc()->data)) {
      return '<div class="pcc pcc-error">PCC not initialized.</div>';
    }

    $max = max(1, (int)$atts['max']);
    $public_only = !empty($atts['public_only']);

    $items = pcc()->data->get_events_slider($max, $public_only);
    if (is_wp_error($items)) {
      return '<div class="pcc pcc-error">' . esc_html($items->get_error_message()) . '</div>';
    }

    ob_start();
    $template = PCC_PLUGIN_DIR . 'includes/templates/events-list.php';
    $atts_local = $atts;
    if (file_exists($template)) {
      $atts = $atts_local;
      include $template;
    } else {
      echo '<div class="pcc pcc-error">Template not found: events-list.php</div>';
    }
    return ob_get_clean();
  }

  public static function calendar_view($atts) {
    $atts = shortcode_atts(array(
      'months' => 2,
      'public_only' => 1,
    ), $atts, 'pcc_calendar');

    if (!function_exists('pcc') || !pcc() || empty(pcc()->data)) {
      return '<div class="pcc pcc-error">PCC not initialized.</div>';
    }

    $months = max(1, (int)$atts['months']);
    $public_only = !empty($atts['public_only']);

    $model = pcc()->data->get_calendar_instances($months, $public_only);
    if (is_wp_error($model)) {
      return '<div class="pcc pcc-error">' . esc_html($model->get_error_message()) . '</div>';
    }

    ob_start();
    $template = PCC_PLUGIN_DIR . 'includes/templates/calendar-view.php';
    if (file_exists($template)) {
      include $template;
    } else {
      echo '<div class="pcc pcc-error">Template not found: calendar-view.php</div>';
    }
    return ob_get_clean();
  }
}