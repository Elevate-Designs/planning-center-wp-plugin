<?php
if (!defined('ABSPATH')) exit;

final class PCC_API {

    public function has_credentials() {
        $s = get_option(PCC_OPTION_KEY, []);
        return !empty($s['app_id']) && (!empty($s['secret']) || !empty($s['secret_enc']));
    }

    public function get_auth_header() {
        $s = get_option(PCC_OPTION_KEY, []);
        $app = trim($s['app_id'] ?? '');
        $sec = trim($s['secret'] ?? '');

        if ($app === '' || $sec === '') return '';
        return 'Basic ' . base64_encode("$app:$sec");
    }

    public function get_json($path) {
        $res = wp_remote_get(
            PCC_API_BASE . $path,
            [
                'headers' => [
                    'Authorization' => $this->get_auth_header(),
                    'Accept' => 'application/json'
                ]
            ]
        );

        if (is_wp_error($res)) return $res;
        return json_decode(wp_remote_retrieve_body($res), true);
    }
}