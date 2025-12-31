<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_API {

    public function has_credentials() {
        $settings = get_option(PCC_OPTION_KEY, array());

        $app_id = trim((string)($settings['app_id'] ?? ''));
        $secret = $this->get_secret();

        return $app_id !== '' && $secret !== '';
    }

    public function get_app_id() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return trim((string)($settings['app_id'] ?? ''));
    }

    public function get_secret() {
        $settings = get_option(PCC_OPTION_KEY, array());

        // ✅ Preferred (encrypted)
        if (!empty($settings['secret_enc'])) {
            return PCC_Crypto::decrypt((string)$settings['secret_enc']);
        }

        // 🔄 Backward compatibility (plain secret)
        if (!empty($settings['secret'])) {
            return trim((string)$settings['secret']);
        }

        return '';
    }

    public function get_auth_header() {
        $app_id = $this->get_app_id();
        $secret = $this->get_secret();

        if ($app_id === '' || $secret === '') {
            return '';
        }

        return 'Basic ' . base64_encode($app_id . ':' . $secret);
    }

    public function request($path_or_url, $params = array(), $method = 'GET', $absolute = false) {
        if (!$this->has_credentials()) {
            return new WP_Error(
                'pcc_missing_credentials',
                __('Planning Center credentials are not set yet.', 'pcc')
            );
        }

        $url = $absolute
            ? $path_or_url
            : rtrim(PCC_API_BASE, '/') . '/' . ltrim($path_or_url, '/');

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => $this->get_auth_header(),
                'Accept'        => 'application/json',
                'User-Agent'    => 'WP-PCC/' . PCC_VERSION . ' (' . home_url('/') . ')',
            ),
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            return new WP_Error(
                'pcc_api_error',
                sprintf(
                    __('Planning Center API request failed (%1$s): %2$s', 'pcc'),
                    $status,
                    esc_url_raw($url)
                ),
                array('status' => $status, 'body' => $body)
            );
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
            return new WP_Error(
                'pcc_bad_json',
                __('Could not decode JSON response from Planning Center.', 'pcc')
            );
        }

        return $json;
    }
}