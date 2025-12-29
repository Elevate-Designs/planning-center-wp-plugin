<?php
/**
 * Uninstall hook for Planning Center Church Integrator
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('pcc_settings');

// Best-effort: clear transients.
delete_transient('pcc_events');
delete_transient('pcc_sermons');
delete_transient('pcc_groups');
