<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

$per_view = isset($atts['per_view']) ? max(1, (int)$atts['per_view']) : 3;

$placeholder = defined('PCC_PLUGIN_URL')
    ? PCC_PLUGIN_URL . 'assets/img/pcc-placeholder.svg'
    : '';
?>
<div class="pcc pcc-events">
  <div class="pcc-events-slider" data-per-view="<?php echo esc_attr($per_view); ?>">
    <button class="pcc-slider-btn pcc-prev" type="button" aria-label="<?php echo esc_attr__('Previous', 'pcc'); ?>">‹</button>

    <div class="pcc-slider-viewport" tabindex="0" aria-label="<?php echo esc_attr__('Events slider', 'pcc'); ?>">
      <div class="pcc-slider-track">
        <?php foreach ($items as $it) :
          $title = (string)($it['title'] ?? '');
          $url   = (string)($it['url'] ?? '');
          $starts = (string)($it['starts_at'] ?? '');
          $ends   = (string)($it['ends_at'] ?? '');
          $desc   = (string)($it['description'] ?? '');
          $img    = (string)($it['image_url'] ?? '');

          if ($img === '') $img = $placeholder;

          // Date formatting (local WP timezone)
          $date_str = '';
          $start_ts = $starts ? strtotime($starts) : 0;
          $end_ts   = $ends ? strtotime($ends) : 0;
          if ($start_ts) {
            $date_str = wp_date('F j, Y', $start_ts);
            $date_str .= ' ' . wp_date(get_option('time_format'), $start_ts);
            if ($end_ts) {
              $date_str .= ' - ' . wp_date(get_option('time_format'), $end_ts);
            }
          }

          $desc_clean = wp_strip_all_tags($desc);
          if (function_exists('mb_strlen') && mb_strlen($desc_clean) > 140) {
            $desc_clean = mb_substr($desc_clean, 0, 140) . '…';
          } elseif (strlen($desc_clean) > 140) {
            $desc_clean = substr($desc_clean, 0, 140) . '…';
          }
        ?>
          <div class="pcc-slide">
            <article class="pcc-event-card">
              <?php if ($url) : ?><a class="pcc-event-link" href="<?php echo esc_url($url); ?>"><?php endif; ?>
                <div class="pcc-event-thumb">
                  <?php if ($img) : ?>
                    <img loading="lazy" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>">
                  <?php endif; ?>
                </div>
                <div class="pcc-event-body">
                  <h3 class="pcc-event-title"><?php echo esc_html($title); ?></h3>
                  <?php if ($date_str) : ?>
                    <p class="pcc-event-meta"><?php echo esc_html($date_str); ?></p>
                  <?php endif; ?>
                  <?php if ($desc_clean) : ?>
                    <p class="pcc-event-desc"><?php echo esc_html($desc_clean); ?></p>
                  <?php endif; ?>
                </div>
              <?php if ($url) : ?></a><?php endif; ?>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button class="pcc-slider-btn pcc-next" type="button" aria-label="<?php echo esc_attr__('Next', 'pcc'); ?>">›</button>
  </div>
</div>