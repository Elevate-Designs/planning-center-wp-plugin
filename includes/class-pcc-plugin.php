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
        $this->includes();
        $this->init_services();
        $this->init_hooks();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        $base = PCC_PLUGIN_DIR . 'includes/';

        // Core helpers
        if (file_exists($base . 'class-pcc-crypto.php')) require_once $base . 'class-pcc-crypto.php';
        if (file_exists($base . 'class-pcc-cache.php'))  require_once $base . 'class-pcc-cache.php';

        // API + Data
        if (file_exists($base . 'class-pcc-api.php'))    require_once $base . 'class-pcc-api.php';
        if (file_exists($base . 'class-pcc-data.php'))   require_once $base . 'class-pcc-data.php';

        // Cron + Shortcodes
        if (file_exists($base . 'class-pcc-cron.php'))       require_once $base . 'class-pcc-cron.php';
        if (file_exists($base . 'class-pcc-shortcodes.php')) require_once $base . 'class-pcc-shortcodes.php';

        // Admin
        if (is_admin()) {
            $admin = $base . 'admin/class-pcc-admin.php';
            if (file_exists($admin)) require_once $admin;
        }
    }

    private function init_services() {
        $this->cache = class_exists('PCC_Cache') ? new PCC_Cache() : null;
        $this->api   = class_exists('PCC_API') ? new PCC_API() : null;

        // IMPORTANT: PCC_Data butuh 2 argumen ($api, $cache)
        if (class_exists('PCC_Data') && $this->api) {
            $this->data = new PCC_Data($this->api, $this->cache);
        } else {
            $this->data = null;
        }
    }

    private function init_hooks() {
        // Shortcodes
        add_action('init', array('PCC_Shortcodes', 'register'));

        // Assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        // Admin
        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
            add_action('admin_post_pcc_refresh_cache', array('PCC_Admin', 'handle_refresh_cache'));
        }

        // Cron
        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
        }
    }

    public function register_assets() {
        // CSS
        $css_path = PCC_PLUGIN_DIR . 'assets/css/frontend.css';
        if (file_exists($css_path)) {
            wp_register_style('pcc-frontend', PCC_PLUGIN_URL . 'assets/css/frontend.css', array(), PCC_VERSION);
            wp_enqueue_style('pcc-frontend');
        }

        // JS Slider
        $js_path = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($js_path)) {
            wp_register_script('pcc-events-slider', PCC_PLUGIN_URL . 'assets/js/events-slider.js', array(), PCC_VERSION, true);
        }
    }
}