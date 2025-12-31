<?php
/**
 * Plugin Name: Planning Center Church Integrator
 * Version: 1.0.14
 */

if (!defined('ABSPATH')) exit;

define('PCC_VERSION', '1.0.14');
define('PCC_PLUGIN_FILE', __FILE__);
define('PCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCC_OPTION_KEY', 'pcc_settings');
define('PCC_API_BASE', 'https://api.planningcenteronline.com');

require_once PCC_PLUGIN_DIR . 'includes/class-pcc-plugin.php';
require_once PCC_PLUGIN_DIR . 'includes/class-pcc-cron.php';

register_activation_hook(__FILE__, ['PCC_Cron', 'activate']);
register_deactivation_hook(__FILE__, ['PCC_Cron', 'deactivate']);

function pcc() {
    return PCC_Plugin::instance();
}

pcc();