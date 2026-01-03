<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar) from Planning Center and display on your WordPress site via shortcodes.
 * Version: 1.0.24
 * Author: Elevate Designs
 * Text Domain: pcc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Constants
 */
define('PCC_VERSION', '1.0.24');
define('PCC_PLUGIN_FILE', __FILE__);
define('PCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCC_OPTION_KEY', 'pcc_settings');
define('PCC_CACHE_GROUP', 'pcc');
define('PCC_API_BASE', 'https://api.planningcenteronline.com');

/**
 * Includes
 */
$inc = PCC_PLUGIN_DIR . 'includes/';
require_once $inc . 'class-pcc-crypto.php';
require_once $inc . 'class-pcc-cache.php';
require_once $inc . 'class-pcc-api.php';
require_once $inc . 'class-pcc-data.php';
require_once $inc . 'class-pcc-shortcodes.php';
require_once $inc . 'class-pcc-cron.php';
require_once $inc . 'class-pcc-plugin.php';

/**
 * Bootstrap helper
 */
function pcc() {
    return class_exists('PCC_Plugin') ? PCC_Plugin::instance() : null;
}

add_action('plugins_loaded', function () {
    pcc();
});

/**
 * Activation / Deactivation
 */
if (class_exists('PCC_Cron')) {
    register_activation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'activate'));
    register_deactivation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'deactivate'));
}

/**
 * GitHub Auto Update (optional)
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
        $updateChecker->getVcsApi()->enableReleaseAssets();
    }
}