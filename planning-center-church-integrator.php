<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them on your WordPress site via shortcodes.
 * Version: 1.0.18
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
define( 'PCC_VERSION', '1.0.18' );
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
 * NOTE:
 * - class-pcc-plugin.php akan include dependency lain (api/data/cache/crypto/admin/shortcodes/cron)
 * - Tapi untuk activation hook, kita include cron juga biar aman.
 */
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-plugin.php';
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';

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
    if ( class_exists( 'PCC_Plugin' ) ) {
        return PCC_Plugin::instance();
    }
    return null;
}

// Bootstrap setelah WP siap
add_action( 'plugins_loaded', 'pcc' );

/**
 * =====================================================
 * GitHub Auto Update (Plugin Update Checker)
 * =====================================================
 */
$updaterPath = PCC_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

if ( file_exists( $updaterPath ) ) {
    require_once $updaterPath;

    if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Elevate-Designs/planning-center-wp-plugin/',
            PCC_PLUGIN_FILE,
            'planning-center-church-integrator'
        );

        // Gunakan GitHub Releases (recommended)
        if ( method_exists( $updateChecker, 'getVcsApi' ) ) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
    }
}
