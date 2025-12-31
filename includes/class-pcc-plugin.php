<?php
if (!defined('ABSPATH')) exit;

final class PCC_Plugin {

    private static $instance = null;

    /** @var PCC_API */
    public $api;

    /** @var PCC_Data */
    public $data;

    private function __construct() {
        $this->includes();
        $this->init();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-api.php';
        require_once PCC_PLUGIN_DIR . 'includes/class-pcc-shortcodes.php';

        // OPTIONAL: kalau ada
        if (file_exists(PCC_PLUGIN_DIR . 'includes/class-pcc-data.php')) {
            require_once PCC_PLUGIN_DIR . 'includes/class-pcc-data.php';
        }
    }

    private function init() {
        $this->api = new PCC_API();

        if (class_exists('PCC_Data')) {
            $this->data = new PCC_Data($this->api);
        }

        add_action('init', ['PCC_Shortcodes', 'register']);
    }
}