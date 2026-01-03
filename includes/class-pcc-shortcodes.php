<?php
if (!defined('ABSPATH')) { exit; }

final class PCC_Shortcodes {

    public static function register() {
        add_shortcode('pcc_events',  array(__CLASS__, 'events_shortcode'));
        add_shortcode('pcc_sermons', array(__CLASS__, 'sermons_shortcode'));
        add_shortcode('pcc_groups',  array(__CLASS__, 'groups_shortcode'));
    }

    /**
     * [pcc_events per_view="3" max="6"]               -> slider default
     * [pcc_events view="calendar"]                    -> calendar grid + popup
     * [pcc_events view="list"]                        -> simple list
     */
    public static function events_shortcode($atts) {

        $atts = shortcode_atts(array(
            'view'     => 'slider', // slider | calendar | list
            'limit'    => 80,       // how many to fetch from API
            'max'      => 6,        // how many to show (slider/list)
            'per_view' => 3,        // slider cards visible
        ), $atts, 'pcc_events');

        $view     = sanitize_key((string)$atts['view']);
        $limit    = max(1, (int)$atts['limit']);
        $max      = max(1, (int)$atts['max']);
        $per_view = max(1, (int)$atts['per_view']);

        $plugin = function_exists('pcc') ? pcc() : null;
        if (!$plugin || empty($plugin->api) || !$plugin->api->has_credentials()) {
            return '<div class="pcc-error">Planning Center credentials are not set.</div>';
        }

        // enqueue CSS always
        wp_enqueue_style('pcc-frontend');

        // Fetch events (public only is enforced inside PCC_Data)
        // For calendar, fetch more so we can render multiple months.
        $fetch_limit = ($view === 'calendar') ? max($limit, 200) : max($limit, $max);
        $events = $plugin->data->get_events(false, $fetch_limit);
        if (is_wp_error($events)) {
            return self::render_error($events);
        }

        if (!is_array($events) || empty($events)) {
            return '<div class="pcc-empty">Events will appear here.</div>';
        }

        if ($view === 'calendar') {
            wp_enqueue_script('pcc-events-calendar');
            return self::render_template('events-calendar.php', array(
                'items' => $events,
                'atts'  => $atts,
            ));
        }

        // slider or list: take max
        $events = array_slice($events, 0, $max);

        if ($view === 'list') {
            return self::render_template('events-plain-list.php', array(
                'items' => $events,
                'atts'  => $atts,
            ));
        }

        // default slider
        wp_enqueue_script('pcc-events-slider');
        return self::render_template('events-list.php', array(
            'items' => $events,
            'atts'  => array(
                'per_view' => $per_view,
                'max'      => $max,
            ),
        ));
    }

    // --- sermons/groups keep as-is (minimal) ---
    public static function sermons_shortcode($atts) {
        return '<div class="pcc-empty">Sermons shortcode not configured in this snippet.</div>';
    }
    public static function groups_shortcode($atts) {
        return '<div class="pcc-empty">Groups shortcode not configured in this snippet.</div>';
    }

    private static function render_error($err) {
        if (!is_wp_error($err)) return '';
        return '<div class="pcc-error">' . esc_html($err->get_error_message()) . '</div>';
    }

    private static function render_template($template_file, $vars = array()) {
        $path = self::locate_template($template_file);
        if (!$path) return '';

        ob_start();
        extract($vars, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    private static function locate_template($template_file) {
        // allow theme override: /wp-content/themes/your-theme/planning-center/<file>
        $theme_path = locate_template('planning-center/' . $template_file);
        if ($theme_path) return $theme_path;

        $plugin_path = PCC_PLUGIN_DIR . 'includes/templates/' . $template_file;
        if (file_exists($plugin_path)) return $plugin_path;

        return '';
    }
}