<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_API {

    public function has_credentials() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return !empty($settings['app_id']) && !empty($settings['secret_enc']);
    }

    public function get_app_id() {
        $settings = get_option(PCC_OPTION_KEY, array());
        return isset($settings['app_id']) ? trim((string) $settings['app_id']) : '';
    }

    public function get_secret() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $enc = isset($settings['secret_enc']) ? (string) $settings['secret_enc'] : '';
        return PCC_Crypto::decrypt($enc);
    }

    public function get_auth_header() {
        $app_id = $this->get_app_id();
        $secret = $this->get_secret();

        if ($app_id === '' || $secret === '') {
            return '';
        }
        return 'Basic ' . base64_encode($app_id . ':' . $secret);
    }

    /**
     * Low-level HTTP request.
     *
     * @param string $path_or_url  Example: '/calendar/v2/event_instances' OR a full URL if $absolute = true.
     * @param array  $params       Query string params.
     * @param string $method       GET/POST/etc.
     * @param bool   $absolute     Whether $path_or_url is already a full URL.
     * @return array|WP_Error
     */
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

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

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
        if (is_wp_error($res)) {
            return $res;
        }

        $json = json_decode($res['body'], true);
        if (!is_array($json)) {
            return new WP_Error('pcc_bad_json', __('Could not decode JSON response from Planning Center.', 'pcc'), array('body' => $res['body']));
        }

        return $json;
    }

    /**
     * Fetch all pages by following JSON:API links.next.
     * NOTE: We cap pages to avoid runaway loops.
     *
     * @return array|WP_Error  A JSON-like array with merged data/included.
     */
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
