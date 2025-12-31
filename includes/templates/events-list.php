<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

$per_view = isset($atts['per_view']) ? max(1, (int)$atts['per_view']) : 3;
?>
<div class="pcc pcc-events-slider" data-per-view="<?php echo esc_attr($per_view); ?>">
    <button class="pcc-slider-btn pcc-prev" type="button" aria-label="Previous">‹</button>

    <div class="pcc-slider-viewport">
        <div class="pcc-slider-track">
            <?php foreach ($items as $it) :
                $title  = isset($it['title']) ? (string)$it['title'] : '';
                $url    = isset($it['url']) ? (string)$it['url'] : '';
                $starts = isset($it['starts_at']) ? (string)$it['starts_at'] : '';
                $ends   = isset($it['ends_at']) ? (string)$it['ends_at'] : '';

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
            <article class="pcc-event-card">
                <h3 class="pcc-event-title">
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($title); ?>
                    <?php endif; ?>
                </h3>

                <?php if ($date_str) : ?>
                    <div class="pcc-event-meta"><?php echo esc_html($date_str); ?></div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <button class="pcc-slider-btn pcc-next" type="button" aria-label="Next">›</button>
</div>