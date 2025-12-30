<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them on your WordPress site via shortcodes.
 * Version: 1.0.2
 * Author: Sagitarisandy
 * Text Domain: pcc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =====================================================
 * Constants
 * =====================================================
 */
define( 'PCC_VERSION', '1.0.1' );
define( 'PCC_PLUGIN_FILE', __FILE__ );
define( 'PCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCC_OPTION_KEY', 'pcc_settings' );
define( 'PCC_CACHE_GROUP', 'pcc' );
define( 'PCC_API_BASE', 'https://api.planningcenteronline.com' );

/**
 * =====================================================
 * Includes
 * =====================================================
 */
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-plugin.php';
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-shortcodes.php';

/**
 * =====================================================
 * Activation / Deactivation
 * =====================================================
 */
register_activation_hook( PCC_PLUGIN_FILE, array( 'PCC_Cron', 'activate' ) );
register_deactivation_hook( PCC_PLUGIN_FILE, array( 'PCC_Cron', 'deactivate' ) );

/**
 * =====================================================
 * Bootstrap
 * =====================================================
 */
function pcc() {
    return PCC_Plugin::instance();
}
pcc();

/**
 * =====================================================
 * GitHub Auto Update (Plugin Update Checker)
 * =====================================================
 */
require_once PCC_PLUGIN_DIR . 'plugin-update-checker-master/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Elevate-Designs/planning-center-wp-plugin/',
    PCC_PLUGIN_FILE,
    'planning-center-church-integrator'
);

// Gunakan GitHub Releases (recommended)
$updateChecker->getVcsApi()->enableReleaseAssets();