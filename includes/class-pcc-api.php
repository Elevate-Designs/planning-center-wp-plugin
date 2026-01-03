<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_API {

    private $last_error = '';

    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get PAT credentials from options (robust untuk beberapa versi field)
     */
    public function get_pat_credentials() {
        $opt = get_option(PCC_OPTION_KEY, array());
        if (!is_array($opt)) $opt = array();

        // Coba beberapa kemungkinan key yang sering kepakai di versi2 berbeda
        $app_id = $opt['app_id'] ?? $opt['application_id'] ?? $opt['client_id'] ?? $opt['pat_app_id'] ?? '';
        $pat    = $opt['pat']    ?? $opt['personal_access_token'] ?? $opt['token'] ?? $opt['pat_token'] ?? '';

        $app_id = is_string($app_id) ? trim($app_id) : '';
        $pat    = is_string($pat) ? trim($pat) : '';

        return array($app_id, $pat);
    }

    public function has_credentials() {
        list($app_id, $pat) = $this->get_pat_credentials();
        return ($app_id !== '' && $pat !== '');
    }

    /**
     * Core request helper
     */
    public function request($path, $query = array(), $method = 'GET') {
        $this->last_error = '';

        if (!$this->has_credentials()) {
            $this->last_error = 'Planning Center credentials are not set (PAT).';
            return new WP_Error('pcc_no_creds', $this->last_error);
        }

        list($app_id, $pat) = $this->get_pat_credentials();

        $url = rtrim(PCC_API_BASE, '/') . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($app_id . ':' . $pat),
            'Accept'        => 'application/json',
        );

        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => $headers,
        );

        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            $this->last_error = $res->get_error_message();
            return $res;
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        $json = json_decode($body, true);
        if (!is_array($json)) $json = array();

        if ($code < 200 || $code >= 300) {
            $msg = 'Planning Center API request failed (' . $code . '): ' . $url;
            $this->last_error = $msg;
            return new WP_Error('pcc_http_' . $code, $msg, array('body' => $body, 'json' => $json));
        }

        return $json;
    }

    /**
     * Calendar v2 event_instances with include=event
     */
    public function get_event_instances($args = array()) {
        $defaults = array(
            'per_page' => 50,
            'include'  => 'event',
            'order'    => 'starts_at',
        );
        $q = array_merge($defaults, $args);

        // Planning Center style for ordering sometimes uses "order"
        // If your API needs "sort", you can also try:
        // $q['sort'] = 'starts_at';

        return $this->request('/calendar/v2/event_instances', $q, 'GET');
    }

    /**
     * Single event (for richer fields if needed)
     */
    public function get_event($event_id) {
        $event_id = trim((string)$event_id);
        if ($event_id === '') return new WP_Error('pcc_bad_event', 'Missing event_id');
        return $this->request('/calendar/v2/events/' . rawurlencode($event_id), array(), 'GET');
    }
}