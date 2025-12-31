<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Plugin {

    private static $instance = null;

    /** @var PCC_API|null */
    public $api;

    /** @var PCC_Cache|null */
    public $cache;

    /** @var PCC_Data|null */
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

        // Shortcodes + cron
        if (file_exists($base . 'class-pcc-shortcodes.php')) require_once $base . 'class-pcc-shortcodes.php';
        if (file_exists($base . 'class-pcc-cron.php'))       require_once $base . 'class-pcc-cron.php';

        // Admin
        if (is_admin()) {
            $admin = $base . 'admin/class-pcc-admin.php';
            if (file_exists($admin)) require_once $admin;
        }
    }

    private function init_services() {
        $this->cache = class_exists('PCC_Cache') ? new PCC_Cache() : null;
        $this->api   = class_exists('PCC_API') ? new PCC_API() : null;

        if (class_exists('PCC_Data') && $this->api) {
            try {
                $ref  = new ReflectionClass('PCC_Data');
                $ctor = $ref->getConstructor();
                $n    = $ctor ? $ctor->getNumberOfParameters() : 0;

                if ($n >= 2) {
                    $this->data = $ref->newInstance($this->api, $this->cache);
                } elseif ($n === 1) {
                    $this->data = $ref->newInstance($this->api);
                } else {
                    $this->data = $ref->newInstance();
                }
            } catch (Throwable $e) {
                // safest fallback for your plugin signature (2 args)
                $this->data = new PCC_Data($this->api, $this->cache);
            }
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
        }

        // Cron (SAFE: no fatal if constants/methods missing)
        if (class_exists('PCC_Cron')) {
            if (method_exists('PCC_Cron', 'register_schedules')) {
                add_action('init', array('PCC_Cron', 'register_schedules'));
            }

            // only add cron hook if class constant exists
            if (defined('PCC_Cron::HOOK') && method_exists('PCC_Cron', 'run')) {
                $hook = constant('PCC_Cron::HOOK');
                if (is_string($hook) && $hook !== '') {
                    add_action($hook, array('PCC_Cron', 'run'));
                }
            }
        }
    }

    public function enqueue_assets() {
        wp_register_style(
            'pcc-frontend',
            PCC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PCC_VERSION
        );
        wp_enqueue_style('pcc-frontend');

        $js_path = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($js_path)) {
            wp_register_script(
                'pcc-events-slider',
                PCC_PLUGIN_URL . 'assets/js/events-slider.js',
                array(),
                PCC_VERSION,
                true
            );
            wp_enqueue_script('pcc-events-slider');
        }
    }
}