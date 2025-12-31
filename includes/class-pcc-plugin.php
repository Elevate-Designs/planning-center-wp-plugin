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

        require_once $base . 'class-pcc-crypto.php';
        require_once $base . 'class-pcc-cache.php';
        require_once $base . 'class-pcc-api.php';
        require_once $base . 'class-pcc-data.php';
        require_once $base . 'class-pcc-shortcodes.php';

        // cron / admin optional
        if (file_exists($base . 'class-pcc-cron.php')) {
            require_once $base . 'class-pcc-cron.php';
        }
        if (is_admin() && file_exists($base . 'admin/class-pcc-admin.php')) {
            require_once $base . 'admin/class-pcc-admin.php';
        }
    }

    private function init_services() {
        $this->cache = new PCC_Cache();
        $this->api   = new PCC_API();

        // ✅ IMPORTANT: PCC_Data constructor harus bisa 2 args (api, cache)
        $this->data  = new PCC_Data($this->api, $this->cache);
    }

    private function init_hooks() {
        add_action('init', array('PCC_Shortcodes', 'register'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
        }

        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
        }
    }

    public function enqueue_assets() {
        wp_register_style('pcc-frontend', PCC_PLUGIN_URL . 'assets/css/frontend.css', array(), PCC_VERSION);
        wp_enqueue_style('pcc-frontend');

        $js_file = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($js_file)) {
            wp_register_script('pcc-events-slider', PCC_PLUGIN_URL . 'assets/js/events-slider.js', array(), PCC_VERSION, true);
            wp_enqueue_script('pcc-events-slider');
        }
    }
}