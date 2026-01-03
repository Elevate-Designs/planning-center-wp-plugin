<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Plugin {

  private static $instance = null;

  /** @var PCC_API */
  public $api;

  /** @var PCC_Data */
  public $data;

  public static function instance() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    $this->includes();
    $this->init_services();
    $this->hooks();
  }

  private function includes() {
    $base = PCC_PLUGIN_DIR . 'includes/';

    require_once $base . 'class-pcc-api.php';
    require_once $base . 'class-pcc-data.php';
    require_once $base . 'class-pcc-shortcodes.php';

    if (is_admin()) {
      $adminFile = $base . 'admin/class-pcc-admin.php';
      if (file_exists($adminFile)) {
        require_once $adminFile;
      }
    }
  }

  private function init_services() {
    $this->api  = new PCC_API();
    $this->data = new PCC_Data($this->api);
  }

  private function hooks() {
    add_action('init', array('PCC_Shortcodes', 'register'));

    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

    if (is_admin() && class_exists('PCC_Admin')) {
      add_action('admin_menu', array('PCC_Admin', 'register_menu'));
      add_action('admin_init', array('PCC_Admin', 'register_settings'));
    }
  }

  public function enqueue_assets() {
    // Always register
    wp_register_style(
      'pcc-frontend',
      PCC_PLUGIN_URL . 'assets/css/frontend.css',
      array(),
      PCC_VERSION
    );

    wp_register_script(
      'pcc-events-slider',
      PCC_PLUGIN_URL . 'assets/js/events-slider.js',
      array(),
      PCC_VERSION,
      true
    );

    wp_register_script(
      'pcc-calendar',
      PCC_PLUGIN_URL . 'assets/js/pcc-calendar.js',
      array(),
      PCC_VERSION,
      true
    );

    // Enqueue only if shortcode exists in content
    $should = false;
    $shouldCal = false;

    if (is_singular()) {
      $post = get_post();
      if ($post && isset($post->post_content)) {
        $should = has_shortcode($post->post_content, 'pcc_events');
        $shouldCal = has_shortcode($post->post_content, 'pcc_calendar');
      }
    } else {
      // safe default
      $should = true;
      $shouldCal = true;
    }

    if ($should || $shouldCal) {
      wp_enqueue_style('pcc-frontend');
    }
    if ($should) {
      wp_enqueue_script('pcc-events-slider');
    }
    if ($shouldCal) {
      wp_enqueue_script('pcc-calendar');
      wp_localize_script('pcc-calendar', 'PCC_CAL', array(
        'placeholder' => PCC_PLUGIN_URL . 'assets/img/pcc-placeholder.svg'
      ));
    }
  }
}