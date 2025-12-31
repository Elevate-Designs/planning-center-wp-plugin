<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Plugin {

    private static $instance = null;

    /** @var PCC_API */
    public $api;

    /** @var PCC_Cache */
    public $cache;

    /** @var PCC_Data */
    public $data;

    private function __construct() {
        $this->includes();

        $this->cache = class_exists('PCC_Cache') ? new PCC_Cache() : null;
        $this->api   = class_exists('PCC_API') ? new PCC_API() : null;

        if (class_exists('PCC_Data') && $this->api) {
            $this->data = new PCC_Data($this->api, $this->cache);
        } else {
            $this->data = null;
        }

        $this->init_hooks();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        // Core dependencies (pastikan file-file ini memang ada)
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-crypto.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cache.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-api.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-data.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-shortcodes.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';

        if (is_admin()) {
            $admin_file = PCC_PLUGIN_DIR . 'includes/admin/class-pcc-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
            }
        }
    }

    private function init_hooks() {
        // shortcodes
        add_action('init', array('PCC_Shortcodes', 'register'));

        // frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // cron
        add_action('init', array('PCC_Cron', 'register_schedules'));
        add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));

        // admin
        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'pcc-frontend',
            PCC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PCC_VERSION
        );

        $slider_js_path = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($slider_js_path)) {
            wp_enqueue_script(
                'pcc-events-slider',
                PCC_PLUGIN_URL . 'assets/js/events-slider.js',
                array(),
                PCC_VERSION,
                true
            );
        }
    }
}