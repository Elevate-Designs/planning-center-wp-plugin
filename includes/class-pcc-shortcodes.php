<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events', array(__CLASS__, 'events_shortcode'));
        add_shortcode('pcc_sermons', array(__CLASS__, 'sermons_shortcode'));
        add_shortcode('pcc_groups', array(__CLASS__, 'groups_shortcode'));
    }

    public static function events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'max'      => 6,
            'per_view' => 3,
        ), $atts);

        // $events = $data->get_events((int)$atts['limit']);
        $events = array_slice($events, 0, (int)$atts['max']);

        return $this->render_template('events-list.php', [
            'events' => $events,
            'atts'   => $atts,
        ]);

        $limit = max(1, intval($atts['limit']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '';
        }

        $json = $plugin->data->get_events(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_event_instances($json);

        return self::render_template('events-list.php', array(
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ));
    }

    public static function sermons_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
        ), $atts, 'pcc_sermons');

        $limit = max(1, intval($atts['limit']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '';
        }

        $json = $plugin->data->get_sermons(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        return self::render_template('sermons-list.php', array(
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ));
    }

    public static function groups_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 20,
        ), $atts, 'pcc_groups');

        $limit = max(1, intval($atts['limit']));

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin) {
            return '';
        }

        $json = $plugin->data->get_groups(false, $limit);
        if (is_wp_error($json)) {
            return self::render_error($json);
        }

        $items = self::normalize_simple_resources($json);

        return self::render_template('groups-list.php', array(
            'items' => array_slice($items, 0, $limit),
            'atts'  => $atts,
        ));
    }

    private static function render_error($err) {
        if (!is_wp_error($err)) {
            return '';
        }
        $msg = esc_html($err->get_error_message());
        return '<div class="pcc-error">' . $msg . '</div>';
    }

    private static function render_template($template_file, $vars = array()) {
        $path = self::locate_template($template_file);
        if (!$path) {
            return '';
        }

        ob_start();
        extract($vars, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    /**
     * Allow theme override:
     * - wp-content/themes/your-theme/planning-center/events-list.php
     * Fallback:
     * - plugin includes/templates/events-list.php
     */
    private static function locate_template($template_file) {
        $theme_path = locate_template('planning-center/' . $template_file);
        if ($theme_path) {
            return $theme_path;
        }

        $plugin_path = PCC_PLUGIN_DIR . 'includes/templates/' . $template_file;
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return '';
    }

    private static function normalize_simple_resources($json) {
        $items = array();
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : array();

        foreach ($data as $node) {
            $attrs = isset($node['attributes']) && is_array($node['attributes']) ? $node['attributes'] : array();

            $items[] = array(
                'id'          => isset($node['id']) ? (string) $node['id'] : '',
                'type'        => isset($node['type']) ? (string) $node['type'] : '',
                'title'       => self::guess_title($attrs),
                'description' => self::guess_description($attrs),
                'url'         => self::guess_url($attrs),
                'date'        => self::guess_date($attrs),
                'raw'         => $node,
            );
        }

        return $items;
    }

    private static function normalize_event_instances($json) {
        $items = array();
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : array();

        // Build a map from included resources
        $included_map = array();
        $included = isset($json['included']) && is_array($json['included']) ? $json['included'] : array();
        foreach ($included as $inc) {
            if (!isset($inc['type'], $inc['id'])) {
                continue;
            }
            $key = $inc['type'] . '/' . $inc['id'];
            $included_map[$key] = $inc;
        }

        foreach ($data as $node) {
            $attrs = isset($node['attributes']) && is_array($node['attributes']) ? $node['attributes'] : array();

            $event_title = '';
            $event_desc  = '';
            $event_url   = '';

            // relationship: event
            $event = null;
            if (isset($node['relationships']['event']['data']['type'], $node['relationships']['event']['data']['id'])) {
                $rel = $node['relationships']['event']['data'];
                $k = $rel['type'] . '/' . $rel['id'];
                if (isset($included_map[$k])) {
                    $event = $included_map[$k];
                }
            }

            if (is_array($event)) {
                $eattrs = isset($event['attributes']) && is_array($event['attributes']) ? $event['attributes'] : array();
                $event_title = self::guess_title($eattrs);
                $event_desc  = self::guess_description($eattrs);
                $event_url   = self::guess_url($eattrs);
            }

            $items[] = array(
                'id'          => isset($node['id']) ? (string) $node['id'] : '',
                'type'        => isset($node['type']) ? (string) $node['type'] : '',
                'title'       => $event_title !== '' ? $event_title : self::guess_title($attrs),
                'description' => $event_desc !== '' ? $event_desc : self::guess_description($attrs),
                'url'         => $event_url !== '' ? $event_url : self::guess_url($attrs),
                'starts_at'   => isset($attrs['starts_at']) ? (string) $attrs['starts_at'] : '',
                'ends_at'     => isset($attrs['ends_at']) ? (string) $attrs['ends_at'] : '',
                'raw'         => $node,
                'event_raw'   => $event,
            );
        }

        return $items;
    }

    private static function guess_title($attrs) {
        foreach (array('title', 'name', 'summary') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) {
                return $attrs[$k];
            }
        }
        return '';
    }

    private static function guess_description($attrs) {
        foreach (array('description', 'details', 'short_description', 'long_description') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) {
                return $attrs[$k];
            }
        }
        return '';
    }

    private static function guess_url($attrs) {
        foreach (array('public_url', 'url', 'church_center_url', 'public_church_center_url') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) {
                return $attrs[$k];
            }
        }
        return '';
    }

    private static function guess_date($attrs) {
        foreach (array('published_at', 'starts_at', 'created_at', 'updated_at') as $k) {
            if (!empty($attrs[$k]) && is_string($attrs[$k])) {
                return $attrs[$k];
            }
        }
        return '';
    }
}
