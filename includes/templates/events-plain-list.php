<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */

function pcc_fmt_range_list($start_iso, $end_iso) {
    $start_ts = $start_iso ? strtotime($start_iso) : 0;
    $end_ts   = $end_iso ? strtotime($end_iso) : 0;
    if (!$start_ts) return '';
    $d = wp_date('F j, Y', $start_ts);
    $t = wp_date(get_option('time_format'), $start_ts);
    if ($end_ts) return $d . ' • ' . $t . ' - ' . wp_date(get_option('time_format'), $end_ts);
    return $d . ' • ' . $t;
}
?>

<div class="pcc pcc-events-list">
  <ul class="pcc-list">
    <?php foreach ($items as $it):
      $title = (string)($it['title'] ?? '');
      $url   = (string)($it['url'] ?? '');
      $when  = pcc_fmt_range_list(($it['starts_at'] ?? ''), ($it['ends_at'] ?? ''));
      $loc   = (string)($it['location'] ?? '');
    ?>
      <li class="pcc-list__item">
        <div class="pcc-list__left">
          <div class="pcc-list__title">
            <?php if ($url): ?>
              <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($title); ?></a>
            <?php else: ?>
              <?php echo esc_html($title); ?>
            <?php endif; ?>
          </div>
          <div class="pcc-list__meta">
            <?php echo esc_html(trim($when . ($loc ? ' • ' . $loc : ''))); ?>
          </div>
        </div>
        <?php if ($url): ?>
          <a class="pcc-btn pcc-btn--primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Detail</a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>