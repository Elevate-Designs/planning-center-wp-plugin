<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PCC_Plugin')):

final class PCC_Plugin {

    /** @var PCC_API */
    public $api;

    /** @var PCC_Cache */
    public $cache;

    /** @var PCC_Data */
    public $data;

    /** @var PCC_Shortcodes */
    public $shortcodes;

    /** @var PCC_Admin */
    public $admin;

    /** @var PCC_Cron */
    public $cron;

    public function __construct() {
        $this->includes();
        $this->init();
    }

    private function includes() {
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cache.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-crypto.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-api.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-data.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-shortcodes.php';
        require_once PCC_PLUGIN_DIR . 'includes/admin/class-pcc-admin.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';
    }

    private function init() {
        $this->cache = new PCC_Cache();
        $this->api   = new PCC_API($this->cache);
        $this->data  = new PCC_Data($this->api, $this->cache);

        $this->shortcodes = new PCC_Shortcodes($this->data);
        $this->admin      = new PCC_Admin($this->api, $this->cache);
        $this->cron       = new PCC_Cron($this->api, $this->cache, $this->data);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // Base CSS (existing)
        wp_enqueue_style(
            'pcc-frontend',
            PCC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            defined('PCC_VERSION') ? PCC_VERSION : null
        );

        // Register slider JS
        wp_register_script(
            'pcc-events-slider',
            PCC_PLUGIN_URL . 'assets/js/events-slider.js',
            array('jquery'),
            defined('PCC_VERSION') ? PCC_VERSION : null,
            true
        );

        // Register calendar assets
        wp_register_script(
            'pcc-events-calendar',
            PCC_PLUGIN_URL . 'assets/js/events-calendar.js',
            array(),
            defined('PCC_VERSION') ? PCC_VERSION : null,
            true
        );

        wp_register_style(
            'pcc-events-calendar',
            PCC_PLUGIN_URL . 'assets/css/events-calendar.css',
            array(),
            defined('PCC_VERSION') ? PCC_VERSION : null
        );

        // Localize calendar config + nonce
        wp_localize_script('pcc-events-calendar', 'pccCalendar', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('pcc_calendar_nonce'),
            'startOfWeek' => (int)get_option('start_of_week', 0),
            'dateFormat'  => (string)get_option('date_format'),
            'timeFormat'  => (string)get_option('time_format'),
        ));

        // Optional: try enqueue automatically if shortcode exists in the main post content
        global $post;
        if (is_a($post, 'WP_Post') && isset($post->post_content)) {
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