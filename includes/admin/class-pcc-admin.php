<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Admin {

    public static function register_menu() {
        add_options_page(
            'Planning Center Integration',
            'Planning Center',
            'manage_options',
            'pcc-settings',
            array(__CLASS__, 'render_page')
        );
    }

    public static function register_settings() {
        register_setting('pcc_settings_group', PCC_OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            'default'           => array(),
        ));
    }

    public static function sanitize_settings($input) {
        $old = get_option(PCC_OPTION_KEY, array());
        if (!is_array($old)) $old = array();
        if (!is_array($input)) $input = array();

        $out = $old;

        $out['oauth_client_id'] = trim((string)($input['oauth_client_id'] ?? $old['oauth_client_id'] ?? ''));

        // client secret: if empty, keep old
        $secret_plain = trim((string)($input['oauth_client_secret'] ?? ''));
        if ($secret_plain !== '') {
            if (class_exists('PCC_Crypto') && method_exists('PCC_Crypto', 'encrypt')) {
                $out['oauth_client_secret_enc'] = (string) PCC_Crypto::encrypt($secret_plain);
            } else {
                // fallback (not ideal)
                $out['oauth_client_secret_enc'] = $secret_plain;
            }
        }

        return $out;
    }

    public static function redirect_uri() {
        return admin_url('admin-post.php?action=pcc_oauth_callback');
    }

    public static function oauth_start() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('pcc_oauth_start');

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !$plugin->api) wp_die('Plugin not ready');

        if (!$plugin->api->has_oauth_client()) {
            wp_redirect(add_query_arg(array('page'=>'pcc-settings','pcc_msg'=>'missing_client'), admin_url('options-general.php')));
            exit;
        }

        $authorize_url = $plugin->api->get_authorize_url(self::redirect_uri(), 'calendar groups publishing');
        wp_redirect($authorize_url);
        exit;
    }

    public static function oauth_callback() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') {
            wp_redirect(add_query_arg(array('page'=>'pcc-settings','pcc_msg'=>'no_code'), admin_url('options-general.php')));
            exit;
        }

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || !$plugin->api) wp_die('Plugin not ready');

        $ok = $plugin->api->exchange_code_for_token($code, self::redirect_uri());
        if (is_wp_error($ok)) {
            wp_redirect(add_query_arg(array(
                'page'=>'pcc-settings',
                'pcc_msg'=>'oauth_failed',
                'pcc_err'=> rawurlencode($ok->get_error_message()),
            ), admin_url('options-general.php')));
            exit;
        }

        wp_redirect(add_query_arg(array('page'=>'pcc-settings','pcc_msg'=>'connected'), admin_url('options-general.php')));
        exit;
    }

    public static function oauth_disconnect() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('pcc_oauth_disconnect');

        $plugin = function_exists('pcc') ? pcc() : null;
        if ($plugin && $plugin->api) {
            $plugin->api->disconnect();
        }

        wp_redirect(add_query_arg(array('page'=>'pcc-settings','pcc_msg'=>'disconnected'), admin_url('options-general.php')));
        exit;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $plugin = function_exists('pcc') ? pcc() : null;
        $api = $plugin ? $plugin->api : null;

        $settings = get_option(PCC_OPTION_KEY, array());
        if (!is_array($settings)) $settings = array();

        $client_id = esc_attr((string)($settings['oauth_client_id'] ?? ''));

        $msg = isset($_GET['pcc_msg']) ? sanitize_text_field(wp_unslash($_GET['pcc_msg'])) : '';
        $err = isset($_GET['pcc_err']) ? sanitize_text_field(wp_unslash($_GET['pcc_err'])) : '';

        ?>
        <div class="wrap">
            <h1>Planning Center Integration (OAuth)</h1>

            <?php if ($msg): ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        if ($msg === 'connected') echo '✅ Connected successfully.';
                        elseif ($msg === 'disconnected') echo '✅ Disconnected.';
                        elseif ($msg === 'missing_client') echo '⚠️ Please set Client ID & Client Secret first.';
                        elseif ($msg === 'no_code') echo '⚠️ OAuth callback missing code.';
                        elseif ($msg === 'oauth_failed') echo '❌ OAuth failed: ' . esc_html($err);
                        else echo esc_html($msg);
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('pcc_settings_group');
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>OAuth Client ID</label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(PCC_OPTION_KEY); ?>[oauth_client_id]" value="<?php echo $client_id; ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>OAuth Client Secret</label></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(PCC_OPTION_KEY); ?>[oauth_client_secret]" value="" class="regular-text" autocomplete="new-password" />
                            <p class="description">Leave blank to keep existing secret.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redirect URI</th>
                        <td>
                            <code><?php echo esc_html(self::redirect_uri()); ?></code>
                            <p class="description">Masukkan ini ke OAuth App settings di Planning Center.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr />

            <h2>Connection</h2>

            <?php if ($api && $api->is_connected()): ?>
                <p>✅ Status: <strong>Connected</strong></p>
                <p>Token expires at: <code><?php echo esc_html($api->get_expires_at()); ?></code></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=pcc_oauth_disconnect')); ?>">
                    <?php wp_nonce_field('pcc_oauth_disconnect'); ?>
                    <?php submit_button('Disconnect', 'secondary'); ?>
                </form>
            <?php else: ?>
                <p>❌ Status: <strong>Not connected</strong></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=pcc_oauth_start')); ?>">
                    <?php wp_nonce_field('pcc_oauth_start'); ?>
                    <?php submit_button('Connect to Planning Center', 'primary'); ?>
                </form>
            <?php endif; ?>

        </div>
        <?php
    }
}