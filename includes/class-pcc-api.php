<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PCC_API {

    private function get_settings() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return is_array($settings) ? $settings : array();
    }

    private function save_settings($settings) {
        if (!is_array($settings)) $settings = array();
        update_option(PCC_OPTION_KEY, $settings);
    }

    private function enc($plain) {
        $plain = (string)$plain;
        if ($plain === '') return '';
        if (class_exists('PCC_Crypto') && method_exists('PCC_Crypto', 'encrypt')) {
            return (string) PCC_Crypto::encrypt($plain);
        }
        return $plain;
    }

    private function dec($enc) {
        $enc = (string)$enc;
        if ($enc === '') return '';
        if (class_exists('PCC_Crypto') && method_exists('PCC_Crypto', 'decrypt')) {
            $v = PCC_Crypto::decrypt($enc);
            return is_string($v) ? $v : '';
        }
        return $enc;
    }

    public function get_client_id() {
        $s = $this->get_settings();
        return trim((string)($s['oauth_client_id'] ?? ''));
    }

    public function get_client_secret() {
        $s = $this->get_settings();
        $enc = (string)($s['oauth_client_secret_enc'] ?? '');
        return trim($this->dec($enc));
    }

    public function has_oauth_client() {
        return ($this->get_client_id() !== '' && $this->get_client_secret() !== '');
    }

    public function get_access_token_raw() {
        $s = $this->get_settings();
        return trim($this->dec((string)($s['oauth_access_token_enc'] ?? '')));
    }

    public function get_refresh_token_raw() {
        $s = $this->get_settings();
        return trim($this->dec((string)($s['oauth_refresh_token_enc'] ?? '')));
    }

    public function get_expires_at() {
        $s = $this->get_settings();
        return (int)($s['oauth_token_expires_at'] ?? 0);
    }

    public function is_connected() {
        return $this->get_access_token(false) !== '';
    }

    /**
     * Get access token, refresh if needed.
     */
    public function get_access_token($auto_refresh = true) {
        $token = $this->get_access_token_raw();
        if ($token === '') return '';

        $expires_at = $this->get_expires_at();
        $now = time();

        // refresh 60s before expiry
        if ($auto_refresh && $expires_at > 0 && ($now >= ($expires_at - 60))) {
            $ref = $this->refresh_access_token();
            if (is_wp_error($ref)) {
                return $token; // fallback old token
            }
            $token = $this->get_access_token_raw();
        }

        return $token;
    }

    /**
     * Build authorize URL (OAuth2 Authorization Code)
     */
    public function get_authorize_url($redirect_uri, $scope = 'calendar groups publishing') {
        $client_id = $this->get_client_id();
        $redirect_uri = (string)$redirect_uri;

        $query = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => $scope,
        );

        // authorize endpoint :contentReference[oaicite:2]{index=2}
        return 'https://api.planningcenteronline.com/oauth/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange code -> tokens
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        if (!$this->has_oauth_client()) {
            return new WP_Error('pcc_oauth_missing_client', __('OAuth Client ID/Secret not set.', 'pcc'));
        }

        $body = array(
            'grant_type'    => 'authorization_code',
            'code'          => (string)$code,
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri'  => (string)$redirect_uri,
        );

        // token endpoint :contentReference[oaicite:3]{index=3}
        $res = wp_remote_post('https://api.planningcenteronline.com/oauth/token', array(
            'timeout' => 20,
            'headers' => array('Accept' => 'application/json'),
            'body'    => $body,
        ));

        if (is_wp_error($res)) return $res;

        $status = (int) wp_remote_retrieve_response_code($res);
        $raw    = (string) wp_remote_retrieve_body($res);
        $json   = json_decode($raw, true);

        if ($status < 200 || $status >= 300 || !is_array($json)) {
            return new WP_Error('pcc_oauth_token_failed', sprintf('OAuth token exchange failed (%d).', $status), array('body' => $raw));
        }

        $access_token  = (string)($json['access_token'] ?? '');
        $refresh_token = (string)($json['refresh_token'] ?? '');
        $expires_in    = (int)($json['expires_in'] ?? 0);

        if ($access_token === '') {
            return new WP_Error('pcc_oauth_token_invalid', 'OAuth token response missing access_token.', array('body' => $raw));
        }

        $settings = $this->get_settings();
        $settings['oauth_access_token_enc']  = $this->enc($access_token);
        if ($refresh_token !== '') {
            $settings['oauth_refresh_token_enc'] = $this->enc($refresh_token);
        }
        $settings['oauth_token_expires_at'] = $expires_in > 0 ? (time() + $expires_in) : 0;

        $this->save_settings($settings);

        return true;
    }

    /**
     * Refresh access token using refresh_token
     */
    public function refresh_access_token() {
        if (!$this->has_oauth_client()) {
            return new WP_Error('pcc_oauth_missing_client', __('OAuth Client ID/Secret not set.', 'pcc'));
        }

        $refresh = $this->get_refresh_token_raw();
        if ($refresh === '') {
            return new WP_Error('pcc_oauth_missing_refresh', __('Refresh token not found. Reconnect OAuth.', 'pcc'));
        }

        $body = array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
        );

        $res = wp_remote_post('https://api.planningcenteronline.com/oauth/token', array(
            'timeout' => 20,
            'headers' => array('Accept' => 'application/json'),
            'body'    => $body,
        ));

        if (is_wp_error($res)) return $res;

        $status = (int) wp_remote_retrieve_response_code($res);
        $raw    = (string) wp_remote_retrieve_body($res);
        $json   = json_decode($raw, true);

        if ($status < 200 || $status >= 300 || !is_array($json)) {
            return new WP_Error('pcc_oauth_refresh_failed', sprintf('OAuth refresh failed (%d).', $status), array('body' => $raw));
        }

        $access_token  = (string)($json['access_token'] ?? '');
        $refresh_token = (string)($json['refresh_token'] ?? '');
        $expires_in    = (int)($json['expires_in'] ?? 0);

        if ($access_token === '') {
            return new WP_Error('pcc_oauth_refresh_invalid', 'OAuth refresh response missing access_token.', array('body' => $raw));
        }

        $settings = $this->get_settings();
        $settings['oauth_access_token_enc'] = $this->enc($access_token);
        if ($refresh_token !== '') {
            $settings['oauth_refresh_token_enc'] = $this->enc($refresh_token);
        }
        $settings['oauth_token_expires_at'] = $expires_in > 0 ? (time() + $expires_in) : 0;

        $this->save_settings($settings);

        return true;
    }

    public function disconnect() {
        $s = $this->get_settings();
        unset($s['oauth_access_token_enc'], $s['oauth_refresh_token_enc'], $s['oauth_token_expires_at']);
        $this->save_settings($s);
    }

    /**
     * HTTP request with Bearer token
     */
    public function request($path_or_url, $params = array(), $method = 'GET', $absolute = false) {
        $token = $this->get_access_token(true);
        if ($token === '') {
            return new WP_Error('pcc_missing_oauth', __('Not connected. Please connect OAuth first.', 'pcc'));
        }

        $url = $absolute ? $path_or_url : (rtrim(PCC_API_BASE, '/') . '/' . ltrim($path_or_url, '/'));
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token, // bearer usage :contentReference[oaicite:4]{index=4}
                'Accept'        => 'application/json',
                'User-Agent'    => 'WP-PCC/' . PCC_VERSION . ' (' . home_url('/') . ')',
            ),
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);

        // If unauthorized, try refresh once then retry
        if ($status === 401) {
            $ref = $this->refresh_access_token();
            if (!is_wp_error($ref)) {
                $token2 = $this->get_access_token(false);
                if ($token2 !== '') {
                    $args['headers']['Authorization'] = 'Bearer ' . $token2;
                    $response = wp_remote_request($url, $args);
                    if (!is_wp_error($response)) {
                        $status = (int) wp_remote_retrieve_response_code($response);
                        $body   = (string) wp_remote_retrieve_body($response);
                    }
                }
            }
        }

        if ($status < 200 || $status >= 300) {
            $message = sprintf(__('Planning Center API request failed (%1$s): %2$s', 'pcc'), $status, $url);
            return new WP_Error('pcc_api_error', $message, array('status' => $status, 'body' => $body));
        }

        return array(
            'status'  => $status,
            'body'    => $body,
            'headers' => wp_remote_retrieve_headers($response),
            'url'     => $url,
        );
    }

    public function get_json($path_or_url, $params = array(), $absolute = false) {
        $res = $this->request($path_or_url, $params, 'GET', $absolute);
        if (is_wp_error($res)) return $res;

        $json = json_decode($res['body'], true);
        if (!is_array($json)) {
            return new WP_Error('pcc_bad_json', __('Could not decode JSON response from Planning Center.', 'pcc'), array('body' => $res['body']));
        }
        return $json;
    }

    public function get_all($path, $params = array(), $max_pages = 10) {
        $page = 0;
        $next_url = null;

        $merged = array(
            'data'     => array(),
            'included' => array(),
            'links'    => array(),
            'meta'     => array(),
        );

        while (true) {
            $page++;

            $json = ($next_url === null)
                ? $this->get_json($path, $params, false)
                : $this->get_json($next_url, array(), true);

            if (is_wp_error($json)) return $json;

            if (isset($json['data']) && is_array($json['data'])) {
                $merged['data'] = array_merge($merged['data'], $json['data']);
            }
            if (isset($json['included']) && is_array($json['included'])) {
                $merged['included'] = array_merge($merged['included'], $json['included']);
            }

            $merged['links'] = isset($json['links']) && is_array($json['links']) ? $json['links'] : $merged['links'];
            $merged['meta']  = isset($json['meta']) && is_array($json['meta']) ? $json['meta'] : $merged['meta'];

            $next_url = (isset($json['links']['next']) && $json['links']['next']) ? $json['links']['next'] : null;

            if ($next_url === null || $page >= $max_pages) break;
        }

        return $merged;
    }
}