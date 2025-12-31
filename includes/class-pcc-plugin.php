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

        // Core deps
        if (file_exists($base . 'class-pcc-crypto.php')) require_once $base . 'class-pcc-crypto.php';
        if (file_exists($base . 'class-pcc-cache.php'))  require_once $base . 'class-pcc-cache.php';
        if (file_exists($base . 'class-pcc-api.php'))    require_once $base . 'class-pcc-api.php';
        if (file_exists($base . 'class-pcc-data.php'))   require_once $base . 'class-pcc-data.php';

        // Features
        if (file_exists($base . 'class-pcc-shortcodes.php')) require_once $base . 'class-pcc-shortcodes.php';
        if (file_exists($base . 'class-pcc-cron.php'))       require_once $base . 'class-pcc-cron.php';

        // Admin
        if (is_admin()) {
            $admin = $base . 'admin/class-pcc-admin.php';
            if (file_exists($admin)) require_once $admin;
        }
    }

    private function init_services() {
        // Cache
        if (class_exists('PCC_Cache')) {
            $this->cache = new PCC_Cache();
        } else {
            // minimal fallback to prevent fatal if cache file missing
            $this->cache = null;
        }

        // API
        $this->api = class_exists('PCC_API') ? new PCC_API() : null;

        // Data (IMPORTANT: expects 2 args in your setup)
        if (class_exists('PCC_Data')) {
            // If PCC_Data has strict type hints, cache must be object; assume file exists in your plugin.
            $this->data = new PCC_Data($this->api, $this->cache);
        } else {
            $this->data = null;
        }
    }

    private function init_hooks() {
        // Shortcodes
        add_action('init', function () {
            if (class_exists('PCC_Shortcodes')) {
                PCC_Shortcodes::register();
            }
        });

        // Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin
        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));

            // OAuth handlers
            add_action('admin_post_pcc_oauth_start', array('PCC_Admin', 'oauth_start'));
            add_action('admin_post_pcc_oauth_callback', array('PCC_Admin', 'oauth_callback'));
            add_action('admin_post_pcc_oauth_disconnect', array('PCC_Admin', 'oauth_disconnect'));
        }

        // Cron (optional)
        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            if (defined('PCC_Cron::HOOK')) {
                add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
            }
        }
    }

    public function enqueue_assets() {
        // CSS
        $css_file = PCC_PLUGIN_DIR . 'assets/css/frontend.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('pcc-frontend', PCC_PLUGIN_URL . 'assets/css/frontend.css', array(), PCC_VERSION);
        }

        // JS slider
        $js_file = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('pcc-events-slider', PCC_PLUGIN_URL . 'assets/js/events-slider.js', array(), PCC_VERSION, true);
        }
    }
}