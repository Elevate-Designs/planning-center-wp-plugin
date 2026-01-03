<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $model */

$months = isset($model['months']) && is_array($model['months']) ? $model['months'] : array();

if (empty($months)) : ?>
  <div class="pcc pcc-calendar">
    <div class="pcc-empty"><?php esc_html_e('No events found for calendar.', 'pcc'); ?></div>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="pcc pcc-calendar" data-pcc-calendar>
  <div class="pcc-cal-toolbar">
    <div class="pcc-cal-search">
      <span class="pcc-cal-search-icon">🔍</span>
      <input type="text" class="pcc-cal-search-input" placeholder="<?php echo esc_attr__('Search for events', 'pcc'); ?>" data-pcc-cal-search />
      <button class="pcc-cal-find-btn" type="button"><?php echo esc_html__('Find Events', 'pcc'); ?></button>
      <button class="pcc-cal-view-btn" type="button"><?php echo esc_html__('Month', 'pcc'); ?> ▾</button>
    </div>
  </div>

  <div class="pcc-cal-monthnav">
    <button type="button" class="pcc-cal-nav pcc-cal-prev" data-pcc-cal-prev>‹</button>
    <button type="button" class="pcc-cal-today" data-pcc-cal-today><?php echo esc_html__('Today', 'pcc'); ?></button>

    <div class="pcc-cal-monthtitle" data-pcc-cal-title></div>

    <div class="pcc-cal-filter">
      <span class="pcc-cal-pill"><?php echo esc_html__('Public times only', 'pcc'); ?></span>
    </div>

    <button type="button" class="pcc-cal-nav pcc-cal-next" data-pcc-cal-next>›</button>
  </div>

  <div class="pcc-cal-months" data-pcc-cal-months>
    <?php foreach ($months as $idx => $m) :
      $label = isset($m['label']) ? (string)$m['label'] : '';
      $weeks = isset($m['weeks']) && is_array($m['weeks']) ? $m['weeks'] : array();
    ?>
      <section class="pcc-cal-month" data-pcc-cal-month data-month-index="<?php echo esc_attr($idx); ?>" <?php echo $idx === 0 ? '' : 'hidden'; ?>>
        <div class="pcc-cal-dow">
          <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
        </div>

        <div class="pcc-cal-grid">
          <?php foreach ($weeks as $week) :
            foreach ($week as $day) :
              $in = !empty($day['in_month']);
              $date = (string)($day['date'] ?? '');
              $dn = (int)($day['day'] ?? 0);
              $events = isset($day['events']) && is_array($day['events']) ? $day['events'] : array();
          ?>
              <div class="pcc-cal-cell <?php echo $in ? '' : 'is-out'; ?>" data-date="<?php echo esc_attr($date); ?>">
                <div class="pcc-cal-daynum"><?php echo esc_html($dn); ?></div>

                <div class="pcc-cal-events">
                  <?php foreach ($events as $ev) :
                    $title = (string)($ev['title'] ?? '');
                    $starts = (string)($ev['starts_at'] ?? '');
                    $ends = (string)($ev['ends_at'] ?? '');
                    $time = '';
                    $st = $starts ? strtotime($starts) : 0;
                    $en = $ends ? strtotime($ends) : 0;
                    if ($st) {
                      $time = wp_date('g:ia', $st);
                      if ($en) $time .= ' - ' . wp_date('g:ia', $en);
                    }

                    $payload = array(
                      'title' => $title,
                      'starts_at' => $starts,
                      'ends_at' => $ends,
                      'time' => $time,
                      'location' => (string)($ev['location'] ?? ''),
                      'address' => (string)($ev['address'] ?? ''),
                      'description' => (string)($ev['description'] ?? ''),
                      'image_url' => (string)($ev['image_url'] ?? ''),
                      'url' => (string)($ev['url'] ?? ''),
                    );
                  ?>
                    <button type="button"
                      class="pcc-cal-event"
                      data-pcc-event="<?php echo esc_attr(wp_json_encode($payload)); ?>">
                      <span class="pcc-cal-event-time"><?php echo esc_html($time); ?></span>
                      <span class="pcc-cal-event-title"><?php echo esc_html($title); ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
          <?php endforeach; endforeach; ?>
        </div>

        <div class="pcc-cal-monthlabel" data-month-label="<?php echo esc_attr($label); ?>"></div>
      </section>
    <?php endforeach; ?>
  </div>

  <!-- Modal -->
  <div class="pcc-modal" data-pcc-modal hidden>
    <div class="pcc-modal-backdrop" data-pcc-modal-close></div>
    <div class="pcc-modal-card" role="dialog" aria-modal="true">
      <button class="pcc-modal-x" type="button" data-pcc-modal-close>×</button>

      <div class="pcc-modal-imgwrap">
        <img data-pcc-modal-img alt="" />
      </div>

      <div class="pcc-modal-body">
        <div class="pcc-modal-time" data-pcc-modal-time></div>
        <h3 class="pcc-modal-title" data-pcc-modal-title></h3>
        <div class="pcc-modal-loc" data-pcc-modal-loc></div>
        <div class="pcc-modal-desc" data-pcc-modal-desc></div>

        <a class="pcc-modal-btn" data-pcc-modal-link href="#" target="_blank" rel="noopener">
          <?php echo esc_html__('Detail', 'pcc'); ?>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  // init month title for first visible month (JS will update as user navigates)
  (function(){
    var root = document.querySelector('[data-pcc-calendar]');
    if(!root) return;
    var first = root.querySelector('[data-pcc-cal-month]:not([hidden]) [data-month-label]');
    var title = root.querySelector('[data-pcc-cal-title]');
    if(first && title) title.textContent = first.getAttribute('data-month-label') || '';
  })();
</script>