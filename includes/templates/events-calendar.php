<?php
if (!defined('ABSPATH')) { exit; }

/** @var int $year */
/** @var int $month */
/** @var bool $public_only */
/** @var bool $show_search */

$year  = isset($year) ? (int)$year : (int)gmdate('Y');
$month = isset($month) ? (int)$month : (int)gmdate('m');

$public_only = !empty($public_only);
$show_search = !empty($show_search);

$month_label = function_exists('wp_date')
  ? wp_date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month)))
  : date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month)));
?>
<div class="pcc pcc-calendar"
     data-initial-year="<?php echo esc_attr($year); ?>"
     data-initial-month="<?php echo esc_attr($month); ?>"
     data-public-only="<?php echo esc_attr($public_only ? '1' : '0'); ?>">

  <div class="pcc-calendar-top">
    <h2 class="pcc-calendar-title"><?php echo esc_html__('Events', 'pcc'); ?></h2>

    <?php if ($show_search): ?>
      <div class="pcc-calendar-search">
        <label class="screen-reader-text" for="pcc-cal-search-<?php echo esc_attr($year . '-' . $month . '-' . wp_rand(10, 9999)); ?>">
          <?php echo esc_html__('Search events', 'pcc'); ?>
        </label>
        <input class="pcc-cal-search" type="search" placeholder="<?php echo esc_attr__('Search for events...', 'pcc'); ?>">
        <button class="pcc-cal-search-btn" type="button"><?php echo esc_html__('Find Events', 'pcc'); ?></button>
      </div>
    <?php endif; ?>

    <div class="pcc-calendar-nav">
      <button class="pcc-cal-prev" type="button" aria-label="<?php echo esc_attr__('Previous month', 'pcc'); ?>">‹</button>
      <button class="pcc-cal-today" type="button"><?php echo esc_html__('Today', 'pcc'); ?></button>
      <div class="pcc-cal-monthwrap">
        <span class="pcc-cal-monthlabel"><?php echo esc_html($month_label); ?></span>
        <select class="pcc-cal-monthselect" aria-label="<?php echo esc_attr__('Select month', 'pcc'); ?>"></select>
      </div>
      <button class="pcc-cal-next" type="button" aria-label="<?php echo esc_attr__('Next month', 'pcc'); ?>">›</button>
    </div>
  </div>

  <div class="pcc-calendar-grid" aria-live="polite"></div>

  <noscript>
    <p><?php echo esc_html__('Please enable JavaScript to view the calendar.', 'pcc'); ?></p>
  </noscript>

  <div class="pcc-cal-popover" hidden></div>
</div>