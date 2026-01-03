<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them on your WordPress site via shortcodes.
 * Version: 1.0.26
 * Author: Sagitarisandy
 * Text Domain: pcc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =====================================================
 * Constants
 * =====================================================
 */
if (!defined('PCC_VERSION')) {
    define('PCC_VERSION', '1.0.26');
}
if (!defined('PCC_PLUGIN_FILE')) {
    define('PCC_PLUGIN_FILE', __FILE__);
}
if (!defined('PCC_PLUGIN_DIR')) {
    define('PCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('PCC_PLUGIN_URL')) {
    define('PCC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('PCC_OPTION_KEY')) {
    define('PCC_OPTION_KEY', 'pcc_settings');
}
if (!defined('PCC_CACHE_GROUP')) {
    define('PCC_CACHE_GROUP', 'pcc');
}
if (!defined('PCC_API_BASE')) {
    define('PCC_API_BASE', 'https://api.planningcenteronline.com');
}

/**
 * =====================================================
 * Includes (minimal - PCC_Plugin will load the rest)
 * =====================================================
 */
$includes_dir = PCC_PLUGIN_DIR . 'includes/';

// Cron class must be available at activation hook registration.
if (file_exists($includes_dir . 'class-pcc-cron.php')) {
    require_once $includes_dir . 'class-pcc-cron.php';
}

// PCC_Plugin is the main service container.
if (file_exists($includes_dir . 'class-pcc-plugin.php')) {
    require_once $includes_dir . 'class-pcc-plugin.php';
}

/**
 * =====================================================
 * Activation / Deactivation
 * =====================================================
 */
if (class_exists('PCC_Cron')) {
    register_activation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'activate'));
    register_deactivation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'deactivate'));
}

/**
 * =====================================================
 * Bootstrap
 * =====================================================
 */
if (!function_exists('pcc')) {
    /**
     * @return PCC_Plugin|null
     */
    function pcc() {
        return class_exists('PCC_Plugin') ? PCC_Plugin::instance() : null;
    }
}

add_action('plugins_loaded', function () {
    // Ensure everything is loaded before init.
    pcc();
}, 5);

/**
 * =====================================================
 * GitHub Auto Update (Plugin Update Checker)
 * =====================================================
 */
$updaterPath = PCC_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($updaterPath)) {
    require_once $updaterPath;

    if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Elevate-Designs/planning-center-wp-plugin/',
            PCC_PLUGIN_FILE,
            'planning-center-church-integrator'
        );

        // Use GitHub Releases assets if present.
        if (method_exists($updateChecker, 'getVcsApi')) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
    }
}