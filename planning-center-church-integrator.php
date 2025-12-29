<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Description: Pull Events (Calendar), Sermons (Publishing Episodes), and Groups from Planning Center and display them on your WordPress site via shortcodes.
 * Version: 1.0.0
 * Author: Sagitarisandy
 * Text Domain: pcc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PCC_VERSION', '1.0.0');
define('PCC_PLUGIN_FILE', __FILE__);
define('PCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCC_OPTION_KEY', 'pcc_settings');

define('PCC_CACHE_GROUP', 'pcc');

define('PCC_API_BASE', 'https://api.planningcenteronline.com');

require_once PCC_PLUGIN_DIR . 'includes/class-pcc-plugin.php';
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';

// Activation / deactivation
register_activation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'activate'));
register_deactivation_hook(PCC_PLUGIN_FILE, array('PCC_Cron', 'deactivate'));

// Bootstrap.
function pcc() {
    return PCC_Plugin::instance();
}

pcc();
