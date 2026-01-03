<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

$per_view = isset($atts['per_view']) ? max(1, (int)$atts['per_view']) : 3;
$uid = 'pcc_slider_' . wp_generate_uuid4();

function pcc_fmt_range($start_iso, $end_iso) {
    $start_ts = $start_iso ? strtotime($start_iso) : 0;
    $end_ts   = $end_iso ? strtotime($end_iso) : 0;
    if (!$start_ts) return '';
    $date = wp_date('F j, Y', $start_ts);
    $time = wp_date(get_option('time_format'), $start_ts);
    if ($end_ts) {
        $time2 = wp_date(get_option('time_format'), $end_ts);
        return $date . ' • ' . $time . ' - ' . $time2;
    }
    return $date . ' • ' . $time;
}
?>

<div class="pcc pcc-events-slider" id="<?php echo esc_attr($uid); ?>" data-per-view="<?php echo esc_attr($per_view); ?>">
  <div class="pcc-slider__header">
    <div class="pcc-slider__title">What’s Happening</div>
    <div class="pcc-slider__controls">
      <button class="pcc-btn pcc-btn--icon" type="button" data-action="prev" aria-label="Previous">‹</button>
      <button class="pcc-btn pcc-btn--icon" type="button" data-action="next" aria-label="Next">›</button>
    </div>
  </div>

  <div class="pcc-slider__track" data-track>
    <?php foreach ($items as $it):
        $title = (string)($it['title'] ?? '');
        $url   = (string)($it['url'] ?? '');
        $img   = (string)($it['image_url'] ?? '');
        $loc   = (string)($it['location'] ?? '');
        $when  = pcc_fmt_range(($it['starts_at'] ?? ''), ($it['ends_at'] ?? ''));

        // simple json for debugging/your request:
        $card_json = array(
            'event-name' => $title,
            'image-url'  => $img,
        );
    ?>
      <article class="pcc-card" data-card data-event='<?php echo esc_attr(wp_json_encode($card_json)); ?>'>
        <div class="pcc-card__media">
          <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
        </div>

        <div class="pcc-card__body">
          <?php if ($when): ?><div class="pcc-card__when"><?php echo esc_html($when); ?></div><?php endif; ?>
          <div class="pcc-card__title"><?php echo esc_html($title); ?></div>
          <?php if ($loc): ?><div class="pcc-card__meta"><?php echo esc_html($loc); ?></div><?php endif; ?>

          <div class="pcc-card__actions">
            <?php if ($url): ?>
              <a class="pcc-btn pcc-btn--primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Detail</a>
            <?php else: ?>
              <span class="pcc-btn pcc-btn--disabled">Detail</span>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>