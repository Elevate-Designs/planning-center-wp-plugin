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

    private function includes() {
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-crypto.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cache.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-api.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-data.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-shortcodes.php';

        if (is_admin()) {
            require_once PCC_PLUGIN_DIR . 'includes/admin/class-pcc-admin.php';
        }
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Frontend
        add_action('init', array('PCC_Shortcodes', 'register'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin
        if (is_admin()) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
        }

        // Cron (cache warmup)
        add_action('init', array('PCC_Cron', 'register_schedules'));
        add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
    }

    // public function enqueue_assets() {
    //     wp_register_style('pcc-frontend', PCC_PLUGIN_URL . 'assets/css/frontend.css', array(), PCC_VERSION);
    //     wp_enqueue_style('pcc-frontend');
    // }

    public function enqueue_assets() {
    // CSS
    wp_enqueue_style(
        'pcc-frontend',
        PCC_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        PCC_VERSION
    );

    // JS - EVENTS SLIDER
    wp_enqueue_script(
        'pcc-events-slider',
        PCC_PLUGIN_URL . 'assets/js/events-slider.js',
        array(),
        PCC_VERSION,
        true // PENTING: load di footer
    );
}


    public function load_textdomain() {
        load_plugin_textdomain('pcc', false, dirname(plugin_basename(PCC_PLUGIN_FILE)) . '/languages');
    }
}
