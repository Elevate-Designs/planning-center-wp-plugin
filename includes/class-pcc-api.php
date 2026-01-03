<?php
if (!defined('ABSPATH')) { exit; }

class PCC_API {

  private $base;

  public function __construct() {
    $this->base = rtrim(PCC_API_BASE, '/');
  }

  public function get($path, $query = array()) {
    return $this->request('GET', $path, $query);
  }

  public function request($method, $path, $query = array(), $body = null) {
    $creds = $this->get_credentials();
    if (empty($creds['app_id']) || empty($creds['secret'])) {
      return new WP_Error('pcc_no_creds', __('Planning Center credentials are not set. Go to Settings → Planning Center.', 'pcc'));
    }

    $url = $this->base . '/' . ltrim($path, '/');
    if (!empty($query)) {
      $url = add_query_arg($query, $url);
    }

    $headers = array(
      'Accept' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode($creds['app_id'] . ':' . $creds['secret']),
    );

    $args = array(
      'method'  => $method,
      'timeout' => 20,
      'headers' => $headers,
    );

    if ($body !== null) {
      $args['headers']['Content-Type'] = 'application/json';
      $args['body'] = wp_json_encode($body);
    }

    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) {
      return $res;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $raw  = (string) wp_remote_retrieve_body($res);

    $json = json_decode($raw, true);

    if ($code >= 400) {
      $msg = 'HTTP ' . $code;
      if (is_array($json) && !empty($json['errors'][0])) {
        $msg = $json['errors'][0]['detail']
          ?? $json['errors'][0]['title']
          ?? $msg;
      }
      return new WP_Error('pcc_http_' . $code, $msg, array('status' => $code, 'url' => $url, 'raw' => $raw));
    }

    if (!is_array($json)) {
      return new WP_Error('pcc_bad_json', __('Invalid JSON from Planning Center.', 'pcc'), array('url' => $url, 'raw' => $raw));
    }

    return $json;
  }

  private function get_credentials() {
    $opt = get_option(PCC_OPTION_KEY, array());
    return array(
      'app_id' => isset($opt['app_id']) ? trim((string)$opt['app_id']) : '',
      'secret' => isset($opt['secret']) ? trim((string)$opt['secret']) : '',
    );
  }
}