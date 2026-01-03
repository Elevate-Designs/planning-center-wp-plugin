<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Plugin {

    private static $instance = null;

    /** @var PCC_API */
    public $api;

    /** @var PCC_Cache */
    public $cache;

    /** @var PCC_Data */
    public $data;

    private function __construct() {
        $this->cache = new PCC_Cache();
        $this->api   = new PCC_API();
        $this->data  = new PCC_Data($this->api, $this->cache);

        $this->init_hooks();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks() {
        add_action('init', array('PCC_Shortcodes', 'register'));

        // Always enqueue frontend assets on frontend (lebih aman untuk Divi)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        if (is_admin()) return;

        $css = PCC_PLUGIN_URL . 'assets/css/frontend.css';
        wp_register_style('pcc-frontend', $css, array(), PCC_VERSION);
        wp_enqueue_style('pcc-frontend');

        $slider_js_path = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($slider_js_path)) {
            wp_register_script('pcc-events-slider', PCC_PLUGIN_URL . 'assets/js/events-slider.js', array(), PCC_VERSION, true);
            wp_enqueue_script('pcc-events-slider');
        }

        $cal_js_path = PCC_PLUGIN_DIR . 'assets/js/events-calendar.js';
        if (file_exists($cal_js_path)) {
            wp_register_script('pcc-events-calendar', PCC_PLUGIN_URL . 'assets/js/events-calendar.js', array(), PCC_VERSION, true);
            wp_enqueue_script('pcc-events-calendar');
        }
    }
}