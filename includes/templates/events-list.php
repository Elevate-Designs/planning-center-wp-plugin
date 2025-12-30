<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

$per_view = isset($atts['per_view']) ? (int)$atts['per_view'] : 3;
?>

<div class="pcc pcc-events">
    <ul class="pcc-list">
        <?php foreach ($items as $it) :
            $title = isset($it['title']) ? $it['title'] : '';
            $url   = isset($it['url']) ? $it['url'] : '';
            $starts = isset($it['starts_at']) ? $it['starts_at'] : '';
            $ends   = isset($it['ends_at']) ? $it['ends_at'] : '';

            $start_ts = $starts ? strtotime($starts) : 0;
            $end_ts   = $ends ? strtotime($ends) : 0;

            $date_str = '';
            if ($start_ts) {
                $date_str = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $start_ts);
                if ($end_ts) {
                    $date_str .= ' — ' . wp_date(get_option('time_format'), $end_ts);
                }
            }
        ?>
            <li class="pcc-item pcc-event">
                <div class="pcc-item__title">
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($title); ?>
                    <?php endif; ?>
                </div>
                <?php if ($date_str) : ?>
                    <div class="pcc-item__meta"><?php echo esc_html($date_str); ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
