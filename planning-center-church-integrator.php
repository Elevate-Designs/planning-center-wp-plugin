<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them on your WordPress site via shortcodes.
 * Version: 1.0.17
 * Author: Sagitarisandy
 * Text Domain: pcc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constants
 */
define('PCC_VERSION', '1.0.17');
define('PCC_PLUGIN_FILE', __FILE__);
define('PCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCC_OPTION_KEY', 'pcc_settings');
define('PCC_CACHE_GROUP', 'pcc');
define('PCC_API_BASE', 'https://api.planningcenteronline.com');

/**
 * Includes (core bootstrap)
 */
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-plugin.php';

/**
 * Activation / Deactivation (safe)
 */
register_activation_hook(PCC_PLUGIN_FILE, function () {
    $cron = PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';
    if (file_exists($cron)) {
        require_once $cron;
        if (class_exists('PCC_Cron') && method_exists('PCC_Cron', 'activate')) {
            PCC_Cron::activate();
        }
    }
});

register_deactivation_hook(PCC_PLUGIN_FILE, function () {
    $cron = PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';
    if (file_exists($cron)) {
        require_once $cron;
        if (class_exists('PCC_Cron') && method_exists('PCC_Cron', 'deactivate')) {
            PCC_Cron::deactivate();
        }
    }
});

/**
 * Bootstrap
 */
function pcc() {
    return PCC_Plugin::instance();
}
pcc();

/**
 * GitHub Auto Update (Plugin Update Checker)
 */
$updaterPath = PCC_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($updaterPath)) {
    require_once $updaterPath;

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/Elevate-Designs/planning-center-wp-plugin/',
        PCC_PLUGIN_FILE,
        'planning-center-church-integrator'
    );

    $updateChecker->getVcsApi()->enableReleaseAssets();
}