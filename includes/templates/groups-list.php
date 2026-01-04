<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */

function pcc_initials($title) {
  $title = trim((string)$title);
  if ($title === '') return '';
  $words = preg_split('/\s+/', $title);
  $ini = '';
  foreach ($words as $w) {
    if ($w === '') continue;
    $ini .= mb_strtoupper(mb_substr($w, 0, 1));
    if (mb_strlen($ini) >= 2) break;
  }
  return $ini;
}
?>
<div class="pcc pcc-groups">
  <div class="pcc-cards">
    <?php foreach ((array)$items as $it) :
      $title = isset($it['title']) ? (string)$it['title'] : '';
      $url   = isset($it['url']) ? (string)$it['url'] : '';
      $desc  = isset($it['description']) ? (string)$it['description'] : '';
      $img   = isset($it['image_url']) ? (string)$it['image_url'] : '';

      // bersihin desc
      $desc_clean = wp_strip_all_tags($desc);
      if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($desc_clean) > 140) $desc_clean = mb_substr($desc_clean, 0, 140) . '…';
      } else {
        if (strlen($desc_clean) > 140) $desc_clean = substr($desc_clean, 0, 140) . '…';
      }

      // validasi URL image (biar gak broken icon)
      $img_ok = ($img && filter_var($img, FILTER_VALIDATE_URL));
      $ini = pcc_initials($title);
    ?>
      <article class="pcc-card pcc-event-card">
        <?php if ($url) : ?><a href="<?php echo esc_url($url); ?>"><?php endif; ?>

          <div class="pcc-event-thumb">
            <?php if ($img_ok) : ?>
              <img loading="lazy" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>">
            <?php else : ?>
              <div class="pcc-thumb-fallback" aria-hidden="true"><?php echo esc_html($ini); ?></div>
            <?php endif; ?>
          </div>

          <div class="pcc-event-body">
            <h3 class="pcc-event-title"><?php echo esc_html($title); ?></h3>
            <?php if ($desc_clean) : ?>
              <p class="pcc-event-desc"><?php echo esc_html($desc_clean); ?></p>
            <?php endif; ?>
          </div>

        <?php if ($url) : ?></a><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>