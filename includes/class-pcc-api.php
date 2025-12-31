<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_API {

    private function get_settings() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return is_array($settings) ? $settings : array();
    }

    public function get_app_id() {
        $s = $this->get_settings();
        return isset($s['app_id']) ? trim((string)$s['app_id']) : '';
    }

    public function get_secret() {
        $s = $this->get_settings();

        // encrypted preferred
        if (!empty($s['secret_enc'])) {
            $dec = PCC_Crypto::decrypt((string)$s['secret_enc']);
            return is_string($dec) ? trim($dec) : '';
        }

        // backward compatible
        if (!empty($s['secret'])) {
            return trim((string)$s['secret']);
        }

        return '';
    }

    public function has_credentials() {
        return ($this->get_app_id() !== '' && $this->get_secret() !== '');
    }

    public function get_auth_header() {
        $id = $this->get_app_id();
        $sec = $this->get_secret();
        if ($id === '' || $sec === '') return '';
        return 'Basic ' . base64_encode($id . ':' . $sec);
    }

    public function request($path_or_url, $params = array(), $method = 'GET', $absolute = false) {
        $auth = $this->get_auth_header();
        if ($auth === '') {
            return new WP_Error('pcc_missing_credentials', __('Planning Center credentials are not set yet.', 'pcc'));
        }

        $url = $absolute
            ? $path_or_url
            : (rtrim(PCC_API_BASE, '/') . '/' . ltrim($path_or_url, '/'));

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
        if (is_wp_error($response)) return $response;

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            $message = sprintf(__('Planning Center API request failed (%1$s): %2$s', 'pcc'), $status, $url);
            return new WP_Error('pcc_api_error', $message, array('status' => $status, 'body' => $body));
        }

        return array(
            'status' => $status,
            'body'   => $body,
            'url'    => $url,
        );
    }

    public function get_json($path_or_url, $params = array(), $absolute = false) {
        $res = $this->request($path_or_url, $params, 'GET', $absolute);
        if (is_wp_error($res)) return $res;

        $json = json_decode($res['body'], true);
        if (!is_array($json)) {
            return new WP_Error('pcc_bad_json', __('Could not decode JSON response from Planning Center.', 'pcc'));
        }
        return $json;
    }
}