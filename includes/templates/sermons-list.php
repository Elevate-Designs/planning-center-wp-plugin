<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */

$placeholder = defined('PCC_PLUGIN_URL')
  ? PCC_PLUGIN_URL . 'assets/img/pcc-placeholder.svg'
  : '';
?>
<div class="pcc pcc-sermons">
  <div class="pcc-cards">
    <?php foreach ($items as $it) :
      $title = isset($it['title']) ? (string)$it['title'] : '';
      $url   = isset($it['url']) ? (string)$it['url'] : '';
      $desc  = isset($it['description']) ? (string)$it['description'] : '';
      $img   = isset($it['image_url']) ? (string)$it['image_url'] : '';
      $pub   = isset($it['published_at']) ? (string)$it['published_at'] : '';

      if ($img === '') { $img = $placeholder; }

      $desc_clean = wp_strip_all_tags($desc);
      if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($desc_clean) > 160) $desc_clean = mb_substr($desc_clean, 0, 160) . '…';
      } else {
        if (strlen($desc_clean) > 160) $desc_clean = substr($desc_clean, 0, 160) . '…';
      }

      $date_str = '';
      $ts = $pub ? strtotime($pub) : 0;
      if ($ts) {
        $date_str = wp_date(get_option('date_format'), $ts);
      }
    ?>
      <article class="pcc-event-card pcc-card">
        <?php if ($url) : ?><a href="<?php echo esc_url($url); ?>"><?php endif; ?>
          <div class="pcc-event-thumb">
            <?php if ($img) : ?>
              <img loading="lazy" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>">
            <?php endif; ?>
          </div>
          <div class="pcc-event-body">
            <h3 class="pcc-event-title"><?php echo esc_html($title); ?></h3>
            <?php if ($date_str) : ?>
              <div class="pcc-event-meta">
                <span class="pcc-event-date"><?php echo esc_html($date_str); ?></span>
              </div>
            <?php endif; ?>
            <?php if ($desc_clean) : ?>
              <p class="pcc-event-desc"><?php echo esc_html($desc_clean); ?></p>
            <?php endif; ?>
          </div>
        <?php if ($url) : ?></a><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>