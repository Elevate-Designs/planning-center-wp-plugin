<?php
// Variables: $settings, $has_secret

if (!defined('ABSPATH')) {
    exit;
}

$notice = isset($_GET['pcc_notice']) ? sanitize_text_field(wp_unslash($_GET['pcc_notice'])) : '';
$test_results = get_transient('pcc_admin_test_results');

?>
<div class="wrap">
    <h1><?php echo esc_html__('Planning Center Integration', 'pcc'); ?></h1>

    <?php if ($notice === 'tested' && is_array($test_results)) : ?>
        <div class="notice notice-info">
            <p><strong><?php echo esc_html__('Connection test results:', 'pcc'); ?></strong></p>
            <ul>
                <?php foreach ($test_results as $k => $v) : ?>
                    <li><?php echo esc_html($k . ': ' . $v); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($notice === 'cache_cleared') : ?>
        <div class="notice notice-success"><p><?php echo esc_html__('Cache cleared.', 'pcc'); ?></p></div>
    <?php elseif ($notice === 'cache_warmed') : ?>
        <div class="notice notice-success"><p><?php echo esc_html__('Cache refreshed (warmed).', 'pcc'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
            settings_fields('pcc_settings_group');
            do_settings_sections('pcc-settings');
            submit_button(__('Save Settings', 'pcc'));
        ?>
    </form>

    <hr />

    <h2><?php echo esc_html__('Utilities', 'pcc'); ?></h2>

    <p><?php echo esc_html__('After saving your credentials, you can test the connection and refresh the cache.', 'pcc'); ?></p>

    <p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
            <?php wp_nonce_field('pcc_test_connection'); ?>
            <input type="hidden" name="action" value="pcc_test_connection" />
            <?php submit_button(__('Test Connection', 'pcc'), 'secondary', 'submit', false); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
            <?php wp_nonce_field('pcc_warm_cache'); ?>
            <input type="hidden" name="action" value="pcc_warm_cache" />
            <?php submit_button(__('Refresh Cache Now', 'pcc'), 'secondary', 'submit', false); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
            <?php wp_nonce_field('pcc_clear_cache'); ?>
            <input type="hidden" name="action" value="pcc_clear_cache" />
            <?php submit_button(__('Clear Cache', 'pcc'), 'delete', 'submit', false); ?>
        </form>
    </p>

    <hr />

    <h2><?php echo esc_html__('How to display on your site', 'pcc'); ?></h2>
    <p><?php echo esc_html__('Use these shortcodes in any Page/Post:', 'pcc'); ?></p>

    <ul>
        <li><code>[pcc_events limit="10"]</code></li>
        <li><code>[pcc_sermons limit="10"]</code></li>
        <li><code>[pcc_groups limit="20"]</code></li>
    </ul>

    <h3><?php echo esc_html__('Template override (optional)', 'pcc'); ?></h3>
    <p>
        <?php echo esc_html__('To customize the HTML, copy templates from:', 'pcc'); ?>
        <code><?php echo esc_html('wp-content/plugins/planning-center-church-integrator/includes/templates/'); ?></code>
        <?php echo esc_html__('to your theme:', 'pcc'); ?>
        <code><?php echo esc_html('wp-content/themes/your-theme/planning-center/'); ?></code>
    </p>

</div>
