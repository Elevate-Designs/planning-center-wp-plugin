<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Plugin')):

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

    /**
     * @return PCC_Plugin
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        $root = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR : dirname(__DIR__) . '/';
        $base = $root . 'includes/';

        // Core deps
        $files = array(
            'class-pcc-crypto.php',
            'class-pcc-cache.php',
            'class-pcc-api.php',
            'class-pcc-data.php',
            'class-pcc-shortcodes.php',
            'class-pcc-cron.php',
        );

        foreach ($files as $file) {
            $path = $base . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Admin (optional) - support both locations to avoid path mistakes.
        if (is_admin()) {
            $admin_candidates = array(
                $base . 'admin/class-pcc-admin.php',
                $base . 'admin/views/class-pcc-admin.php',
            );
            foreach ($admin_candidates as $admin_path) {
                if (file_exists($admin_path)) {
                    require_once $admin_path;
                    break;
                }
            }
        }
    }

    private function init_services() {
        // Cache first.
        if (class_exists('PCC_Cache')) {
            $this->cache = new PCC_Cache();
        }

        // API (PAT via HTTP Basic).
        if (class_exists('PCC_API')) {
            $this->api = new PCC_API();
        }

        // Data service (expects API + optional cache).
        if (class_exists('PCC_Data')) {
            $api = $this->api;
            $cache = $this->cache ?? null;
            $this->data = new PCC_Data($api, $cache);
        }
    }

    private function init_hooks() {
        // Shortcodes.
        if (class_exists('PCC_Shortcodes')) {
            add_action('init', array('PCC_Shortcodes', 'register'));
        }

        // Frontend assets.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin menu/settings.
        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
            add_action('admin_post_pcc_refresh_cache', array('PCC_Admin', 'handle_refresh_cache'));
        }

        // Cron.
        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
        }
    }

    public function enqueue_assets() {
        $root_dir = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR : dirname(__DIR__) . '/';
        $root_url = defined('PCC_PLUGIN_URL') ? PCC_PLUGIN_URL : plugins_url('/', dirname(__FILE__));

        // CSS
        $css_rel = 'assets/css/frontend.css';
        if (file_exists($root_dir . $css_rel)) {
            wp_enqueue_style('pcc-frontend', $root_url . $css_rel, array(), defined('PCC_VERSION') ? PCC_VERSION : null);
        }

        // Optional JS slider
        $js_rel = 'assets/js/events-slider.js';
        if (file_exists($root_dir . $js_rel)) {
            wp_enqueue_script('pcc-events-slider', $root_url . $js_rel, array(), defined('PCC_VERSION') ? PCC_VERSION : null, true);
        }
    }
}

endif;
