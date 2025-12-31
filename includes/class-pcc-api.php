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
        $settings = $this->get_settings();
        return isset($settings['app_id']) ? trim((string) $settings['app_id']) : '';
    }

    public function get_secret() {
        $settings = $this->get_settings();

        // Preferred encrypted
        if (!empty($settings['secret_enc'])) {
            $enc = (string) $settings['secret_enc'];
            $dec = PCC_Crypto::decrypt($enc);
            $dec = is_string($dec) ? trim($dec) : '';
            if ($dec !== '') {
                return $dec;
            }
        }

        // Backward compatible (plain)
        if (!empty($settings['secret'])) {
            return trim((string) $settings['secret']);
        }

        return '';
    }

    public function has_credentials() {
        return ($this->get_app_id() !== '' && $this->get_secret() !== '');
    }

    public function get_auth_header() {
        $app_id = $this->get_app_id();
        $secret = $this->get_secret();

        if ($app_id === '' || $secret === '') {
            return '';
        }

        // PAT: Basic base64(PAT_ID:PAT_SECRET)
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
            $msg = sprintf(__('Planning Center API request failed (%1$s): %2$s', 'pcc'), $status, $url);

            // Biar jelas kalau 401
            if ($status === 401) {
                $msg .= ' — Unauthorized (cek Personal Access Token ID/Secret).';
            }

            return new WP_Error('pcc_api_error', $msg, array('status' => $status, 'body' => $body));
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
        if (is_wp_error($res)) {
            return $res;
        }

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

            if (is_wp_error($json)) {
                return $json;
            }

            if (isset($json['data']) && is_array($json['data'])) {
                $merged['data'] = array_merge($merged['data'], $json['data']);
            }

            if (isset($json['included']) && is_array($json['included'])) {
                $merged['included'] = array_merge($merged['included'], $json['included']);
            }

            $merged['links'] = isset($json['links']) && is_array($json['links']) ? $json['links'] : $merged['links'];
            $merged['meta']  = isset($json['meta']) && is_array($json['meta']) ? $json['meta'] : $merged['meta'];

            $next_url = $this->extract_next_url($json);

            if ($next_url === null || $page >= $max_pages) {
                break;
            }
        }

        return $merged;
    }

    private function extract_next_url($json) {
        if (!is_array($json)) {
            return null;
        }
        if (isset($json['links']) && is_array($json['links']) && !empty($json['links']['next'])) {
            return $json['links']['next'];
        }
        return null;
    }
}