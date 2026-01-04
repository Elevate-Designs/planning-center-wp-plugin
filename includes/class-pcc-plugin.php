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

        if (is_admin()) {
            $admin_path = $base . 'admin/class-pcc-admin.php';
            if (file_exists($admin_path)) {
                require_once $admin_path;
            }
        }
    }

    private function init_services() {
        if (class_exists('PCC_Cache')) {
            $this->cache = new PCC_Cache();
        }

        if (class_exists('PCC_API')) {
            $this->api = new PCC_API();
        }

        if (class_exists('PCC_Data')) {
            $api = $this->api;
            $cache = $this->cache ?? null;
            $this->data = new PCC_Data($api, $cache);
        }
    }

    private function init_hooks() {
        if (class_exists('PCC_Shortcodes')) {
            add_action('init', function () {
                // pastikan service data siap
                if (isset($this->data) && is_object($this->data)) {
                    PCC_Shortcodes::register($this->data);
                }
            });
        }

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
            add_action('admin_post_pcc_refresh_cache', array('PCC_Admin', 'handle_refresh_cache'));
        }

        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
        }
    }

    public function enqueue_assets() {
        $root_dir = defined('PCC_PLUGIN_DIR') ? PCC_PLUGIN_DIR : dirname(__DIR__) . '/';
        $root_url = defined('PCC_PLUGIN_URL') ? PCC_PLUGIN_URL : plugins_url('/', dirname(__FILE__));

        // Base CSS
        $css_rel = 'assets/css/frontend.css';
        if (file_exists($root_dir . $css_rel)) {
            wp_enqueue_style('pcc-frontend', $root_url . $css_rel, array(), defined('PCC_VERSION') ? PCC_VERSION : null);
        }

        // Register slider JS
        $slider_js = 'assets/js/events-slider.js';
        if (file_exists($root_dir . $slider_js)) {
            wp_register_script('pcc-events-slider', $root_url . $slider_js, array(), defined('PCC_VERSION') ? PCC_VERSION : null, true);
        }

        // Register calendar JS/CSS
        $cal_js  = 'assets/js/events-calendar.js';
        $cal_css = 'assets/css/events-calendar.css';

        if (file_exists($root_dir . $cal_js)) {
            wp_register_script('pcc-events-calendar', $root_url . $cal_js, array(), defined('PCC_VERSION') ? PCC_VERSION : null, true);

            wp_localize_script('pcc-events-calendar', 'pccCalendar', array(
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('pcc_calendar_nonce'),
                'startOfWeek' => (int) get_option('start_of_week', 0),
                'dateFormat'  => (string) get_option('date_format'),
                'timeFormat'  => (string) get_option('time_format'),
            ));
        }

        if (file_exists($root_dir . $cal_css)) {
            wp_register_style('pcc-events-calendar', $root_url . $cal_css, array(), defined('PCC_VERSION') ? PCC_VERSION : null);
        }

        // Optional auto-enqueue if shortcode exists in main post content
        global $post;
        if (is_a($post, 'WP_Post') && !empty($post->post_content)) {
            if (has_shortcode($post->post_content, 'pcc_events') || has_shortcode($post->post_content, 'pcc_events_slider')) {
                wp_enqueue_script('pcc-events-slider');
            }
            if (has_shortcode($post->post_content, 'pcc_events_calendar')) {
                wp_enqueue_script('pcc-events-calendar');
                wp_enqueue_style('pcc-events-calendar');
            }
        }
    }
}

endif;