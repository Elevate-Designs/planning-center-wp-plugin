<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PCC_API {

    private function get_settings() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return is_array($settings) ? $settings : array();
    }

    public function get_app_id() {
        $s = $this->get_settings();

        // PAT standard key
        if (!empty($s['app_id'])) return trim((string)$s['app_id']);

        // compatibility: kalau sebelumnya keburu pakai key OAuth UI
        if (!empty($s['oauth_client_id'])) return trim((string)$s['oauth_client_id']);
        if (!empty($s['client_id'])) return trim((string)$s['client_id']);

        return '';
    }

    public function get_secret() {
        $s = $this->get_settings();

        // PAT encrypted
        if (!empty($s['secret_enc'])) {
            $dec = class_exists('PCC_Crypto') ? PCC_Crypto::decrypt((string)$s['secret_enc']) : '';
            $dec = is_string($dec) ? trim($dec) : '';
            if ($dec !== '') return $dec;
        }

        // PAT plain fallback (legacy)
        if (!empty($s['secret'])) {
            return trim((string)$s['secret']);
        }

        // compatibility: OAuth keys pernah dipakai untuk nyimpen “secret”
        if (!empty($s['oauth_client_secret_enc'])) {
            $dec = class_exists('PCC_Crypto') ? PCC_Crypto::decrypt((string)$s['oauth_client_secret_enc']) : '';
            $dec = is_string($dec) ? trim($dec) : '';
            if ($dec !== '') return $dec;
        }
        if (!empty($s['oauth_client_secret'])) return trim((string)$s['oauth_client_secret']);
        if (!empty($s['client_secret'])) return trim((string)$s['client_secret']);

        return '';
    }

    public function has_credentials() {
        return ($this->get_app_id() !== '' && $this->get_secret() !== '');
    }

    public function get_auth_header() {
        $app_id = $this->get_app_id();
        $secret = $this->get_secret();
        if ($app_id === '' || $secret === '') return '';
        return 'Basic ' . base64_encode($app_id . ':' . $secret);
    }

    public function request($path_or_url, $params = array(), $method = 'GET', $absolute = false) {
        $auth = $this->get_auth_header();
        if ($auth === '') {
            return new WP_Error('pcc_missing_credentials', __('Planning Center credentials are not set yet.', 'pcc'));
        }

        $url = $absolute ? $path_or_url : (rtrim(PCC_API_BASE, '/') . '/' . ltrim($path_or_url, '/'));
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => $auth,
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

        if ($status < 200 || $status >= 300) {
            $hint = '';
            if ($status === 401) {
                $hint = ' (401 Unauthorized) - Pastikan yang dipakai adalah Personal Access Token (Application ID & Secret), bukan OAuth Client ID/Secret.';
            }

            $message = sprintf(
                __('Planning Center API request failed (%1$s): %2$s%3$s', 'pcc'),
                $status,
                $url,
                $hint
            );

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

            if (!empty($json['data']) && is_array($json['data'])) {
                $merged['data'] = array_merge($merged['data'], $json['data']);
            }
            if (!empty($json['included']) && is_array($json['included'])) {
                $merged['included'] = array_merge($merged['included'], $json['included']);
            }

            if (!empty($json['links']) && is_array($json['links'])) $merged['links'] = $json['links'];
            if (!empty($json['meta']) && is_array($json['meta']))   $merged['meta']  = $json['meta'];

            $next_url = (!empty($json['links']['next']) ? $json['links']['next'] : null);

            if ($next_url === null || $page >= $max_pages) break;
        }

        return $merged;
    }
}