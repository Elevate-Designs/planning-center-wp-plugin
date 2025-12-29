<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Cache {

    const KEY_EVENTS  = 'pcc_events';
    const KEY_SERMONS = 'pcc_sermons';
    const KEY_GROUPS  = 'pcc_groups';

    public function get_ttl_seconds() {
        $settings = get_option(PCC_OPTION_KEY, array());
        $ttl = isset($settings['cache_ttl']) ? intval($settings['cache_ttl']) : 900;
        if ($ttl < 60) {
            $ttl = 60;
        }
        return $ttl;
    }

    public function get($key) {
        return get_transient($key);
    }

    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->get_ttl_seconds();
        }
        set_transient($key, $value, $ttl);
    }

    public function delete($key) {
        delete_transient($key);
    }

    public function clear_all() {
        $this->delete(self::KEY_EVENTS);
        $this->delete(self::KEY_SERMONS);
        $this->delete(self::KEY_GROUPS);
    }
}
