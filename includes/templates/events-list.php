<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

$per_view = isset($atts['per_view']) ? max(1, (int)$atts['per_view']) : 3;
$view_more_url   = isset($atts['view_more_url']) ? trim((string)$atts['view_more_url']) : '';
$view_more_label = isset($atts['view_more_label']) ? trim((string)$atts['view_more_label']) : 'View more';

$count = is_array($items) ? count($items) : 0;
?>

<div class="pcc pcc-events">
    <div class="pcc-events-slider" data-per-view="<?php echo esc_attr($per_view); ?>" data-count="<?php echo esc_attr($count); ?>">
        <button class="pcc-slider-btn pcc-prev" type="button" aria-label="Previous" <?php echo ($count <= $per_view) ? 'style="display:none"' : ''; ?>>‹</button>

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
                        <div class="pcc-event-card__title">
                            <?php if ($url) : ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($title); ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($date_str) : ?>
                            <div class="pcc-event-card__meta"><?php echo esc_html($date_str); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="pcc-slider-btn pcc-next" type="button" aria-label="Next" <?php echo ($count <= $per_view) ? 'style="display:none"' : ''; ?>>›</button>
    </div>

    <?php if ($view_more_url !== '') : ?>
        <div class="pcc-view-more">
            <a class="pcc-view-more__link" href="<?php echo esc_url($view_more_url); ?>">
                <?php echo esc_html($view_more_label); ?>
            </a>
        </div>
    <?php endif; ?>
</div>