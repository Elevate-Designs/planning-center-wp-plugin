<?php
if (!defined('ABSPATH')) { exit; }

/** @var array $items */

// Build month buckets
$tz = wp_timezone();
$months = array();

// helper: month key from starts_at
foreach ($items as $it) {
    $starts = (string)($it['starts_at'] ?? '');
    if (!$starts) continue;

    $ts = strtotime($starts);
    if (!$ts) continue;

    $monthKey = wp_date('Y-m', $ts); // local
    if (!isset($months[$monthKey])) $months[$monthKey] = array();
    $months[$monthKey][] = $it;
}

$monthKeys = array_keys($months);
sort($monthKeys);

$placeholder = defined('PCC_PLUGIN_URL') ? PCC_PLUGIN_URL . 'assets/img/pcc-placeholder.svg' : '';
?>
<div class="pcc pcc-cal" data-placeholder="<?php echo esc_attr($placeholder); ?>">
  <div class="pcc-cal-header">
    <div class="pcc-cal-search">
      <span style="opacity:.6;">🔎</span>
      <input class="pcc-cal-q" type="text" placeholder="Search for events">
      <button class="pcc-cal-btn" type="button">Find Events</button>
    </div>
    <button class="pcc-cal-btn" type="button">Month ▾</button>
  </div>

  <div class="pcc-cal-controls">
    <div class="pcc-cal-nav">
      <button class="pcc-cal-iconbtn pcc-cal-prev" type="button" aria-label="Prev">‹</button>
      <button class="pcc-cal-iconbtn pcc-cal-next" type="button" aria-label="Next">›</button>
      <button class="pcc-cal-today" type="button">Today</button>
    </div>
    <div class="pcc-cal-titlewrap">
      <span class="pcc-cal-title"></span>
      <span class="pcc-cal-sub">Public times only</span>
    </div>
  </div>

  <?php foreach ($monthKeys as $mi => $mKey): ?>
    <?php
      $mEvents = $months[$mKey];
      $mStart = new DateTime($mKey . '-01 00:00:00', $tz);
      $gridStart = (clone $mStart)->modify('sunday last week')->setTime(0,0,0);
      // Make sure start aligns to sunday of the week containing 1st
      if ($mStart->format('w') == 0) { // Sunday
        $gridStart = (clone $mStart);
      } else {
        $gridStart = (clone $mStart)->modify('last sunday');
      }
      $gridEnd = (clone $mStart)->modify('last day of this month')->setTime(23,59,59);
      // extend to saturday
      if ($gridEnd->format('w') != 6) {
        $gridEnd->modify('next saturday')->setTime(23,59,59);
      }

      // group events by date Y-m-d
      $byDay = array();
      foreach ($mEvents as $ev) {
        $ts = strtotime((string)($ev['starts_at'] ?? ''));
        if (!$ts) continue;
        $dayKey = wp_date('Y-m-d', $ts);
        if (!isset($byDay[$dayKey])) $byDay[$dayKey] = array();
        $byDay[$dayKey][] = $ev;
      }
    ?>

    <div class="pcc-month" data-month="<?php echo esc_attr($mKey); ?>" style="<?php echo $mi===0 ? '' : 'display:none;'; ?>">
      <div class="pcc-grid">
        <div class="pcc-grid-head">
          <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
        </div>

        <div class="pcc-grid-body">
          <?php
            $cur = clone $gridStart;
            while ($cur <= $gridEnd) :
              $dayKey = $cur->format('Y-m-d');
              $dayNum = $cur->format('j');
              $inMonth = ($cur->format('Y-m') === $mKey);
              $cellStyle = $inMonth ? '' : 'opacity:.35;';
          ?>
            <div class="pcc-day" data-day="<?php echo esc_attr($dayKey); ?>" style="<?php echo esc_attr($cellStyle); ?>">
              <div class="pcc-day-num"><?php echo esc_html($dayNum); ?></div>

              <?php if (!empty($byDay[$dayKey])): ?>
                <?php foreach ($byDay[$dayKey] as $ev):
                  $title = (string)($ev['title'] ?? '');
                  $url   = (string)($ev['url'] ?? '');
                  $img   = (string)($ev['image_url'] ?? '');
                  if ($img === '') $img = $placeholder;

                  $start_ts = strtotime((string)($ev['starts_at'] ?? ''));
                  $end_ts   = strtotime((string)($ev['ends_at'] ?? ''));
                  $timeStr = $start_ts ? wp_date('g:i a', $start_ts) : '';
                  if ($end_ts) $timeStr .= ' - ' . wp_date('g:i a', $end_ts);

                  $loc = (string)($ev['location'] ?? '');
                  $desc = wp_strip_all_tags((string)($ev['description'] ?? ''));
                  if (function_exists('mb_strlen') && mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '…';
                ?>
                  <div class="pcc-evt"
                       data-title="<?php echo esc_attr($title); ?>"
                       data-url="<?php echo esc_url($url); ?>"
                       data-img="<?php echo esc_url($img); ?>"
                       data-date="<?php echo esc_attr($start_ts ? wp_date('F j, Y', $start_ts) : ''); ?>"
                       data-time="<?php echo esc_attr($timeStr); ?>"
                       data-loc="<?php echo esc_attr($loc); ?>"
                       data-desc="<?php echo esc_attr($desc); ?>">
                    <?php echo esc_html($title); ?>
                    <?php if ($timeStr): ?>
                      <span class="pcc-evt-time"><?php echo esc_html($timeStr); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

            </div>
          <?php
              $cur->modify('+1 day');
            endwhile;
          ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Popup -->
  <div class="pcc-pop" id="pcc-pop">
    <button class="pcc-pop-close" type="button" aria-label="Close">✕</button>
    <div class="pcc-pop-img"><img alt="" src=""></div>
    <div class="pcc-pop-body">
      <p class="pcc-pop-date"></p>
      <h3 class="pcc-pop-title"></h3>
      <p class="pcc-pop-loc"></p>
      <p class="pcc-pop-desc"></p>
      <div class="pcc-pop-actions">
        <a class="pcc-pop-link" href="#" target="_blank" rel="noopener">Detail</a>
      </div>
    </div>
  </div>
</div>