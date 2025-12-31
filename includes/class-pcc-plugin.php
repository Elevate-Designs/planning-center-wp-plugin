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
        $this->init_hooks();

        // Init core services
        if (class_exists('PCC_Cache')) {
            $this->cache = new PCC_Cache();
        }

        $this->api = new PCC_API();

        // ✅ PCC_Data: constructor expects 2 args in your plugin
        if (class_exists('PCC_Data')) {
            // If cache class exists, pass it; otherwise pass null to avoid fatal
            $cacheObj = $this->cache ?? null;

            try {
                // Robust: handle constructor signature differences safely
                $ref = new ReflectionClass('PCC_Data');
                $ctor = $ref->getConstructor();
                $n = $ctor ? $ctor->getNumberOfParameters() : 0;

                if ($n >= 2) {
                    $this->data = $ref->newInstance($this->api, $cacheObj);
                } elseif ($n === 1) {
                    $this->data = $ref->newInstance($this->api);
                } else {
                    $this->data = $ref->newInstance();
                }
            } catch (Exception $e) {
                // Fallback (should never happen)
                $this->data = new PCC_Data($this->api, $cacheObj);
            }
        }
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        // IMPORTANT: include all dependency classes used by Data/API/Admin
        $base = PCC_PLUGIN_DIR . 'includes/';

        // crypto/cache/api/data
        if (file_exists($base . 'class-pcc-crypto.php')) require_once $base . 'class-pcc-crypto.php';
        if (file_exists($base . 'class-pcc-cache.php'))  require_once $base . 'class-pcc-cache.php';
        if (file_exists($base . 'class-pcc-api.php'))    require_once $base . 'class-pcc-api.php';
        if (file_exists($base . 'class-pcc-data.php'))   require_once $base . 'class-pcc-data.php';

        // cron + shortcodes
        if (file_exists($base . 'class-pcc-cron.php'))       require_once $base . 'class-pcc-cron.php';
        if (file_exists($base . 'class-pcc-shortcodes.php')) require_once $base . 'class-pcc-shortcodes.php';

        // admin (optional)
        if (is_admin()) {
            $admin = $base . 'admin/class-pcc-admin.php';
            if (file_exists($admin)) require_once $admin;
        }
    }

    private function init_hooks() {
        // Frontend
        add_action('init', array('PCC_Shortcodes', 'register'));

        // Assets (CSS/JS) - if file exists
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin (optional)
        if (is_admin() && class_exists('PCC_Admin')) {
            add_action('admin_menu', array('PCC_Admin', 'register_menu'));
            add_action('admin_init', array('PCC_Admin', 'register_settings'));
        }

        // Cron (optional)
        if (class_exists('PCC_Cron')) {
            add_action('init', array('PCC_Cron', 'register_schedules'));
            add_action(PCC_Cron::HOOK, array('PCC_Cron', 'run'));
        }
    }

    public function enqueue_assets() {
        // CSS
        $css = PCC_PLUGIN_URL . 'assets/css/frontend.css';
        wp_register_style('pcc-frontend', $css, array(), PCC_VERSION);
        wp_enqueue_style('pcc-frontend');

        // JS slider (optional if you have it)
        $js_path = PCC_PLUGIN_DIR . 'assets/js/events-slider.js';
        if (file_exists($js_path)) {
            wp_register_script('pcc-events-slider', PCC_PLUGIN_URL . 'assets/js/events-slider.js', array(), PCC_VERSION, true);
            wp_enqueue_script('pcc-events-slider');
        }
    }
}