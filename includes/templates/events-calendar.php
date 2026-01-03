<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */
/** @var array $atts */

// Prepare a clean JSON payload for JS
$payload = array();
foreach ($items as $it) {
    $payload[] = array(
        'id'          => (string)($it['id'] ?? ''),
        'title'       => (string)($it['title'] ?? ''),
        'url'         => (string)($it['url'] ?? ''),
        'starts_at'   => (string)($it['starts_at'] ?? ''),
        'ends_at'     => (string)($it['ends_at'] ?? ''),
        'location'    => (string)($it['location'] ?? ''),
        'description' => (string)($it['description'] ?? ''),
        'image_url'   => (string)($it['image_url'] ?? ''),
    );
}

$uid = 'pcc_cal_' . wp_generate_uuid4();
?>

<div class="pcc pcc-calendar" id="<?php echo esc_attr($uid); ?>"
     data-events="<?php echo esc_attr(wp_json_encode($payload)); ?>">

  <div class="pcc-calendar__top">
    <div class="pcc-calendar__title">Events</div>

    <div class="pcc-calendar__searchrow">
      <div class="pcc-calendar__search">
        <span class="pcc-calendar__searchicon">🔍</span>
        <input type="text" placeholder="Search events" data-search />
      </div>

      <button type="button" class="pcc-btn pcc-btn--dark" data-find>Find Events</button>

      <div class="pcc-calendar__dropdown">
        <button type="button" class="pcc-btn pcc-btn--ghost" data-month-menu>
          Month <span class="pcc-caret">▾</span>
        </button>
        <div class="pcc-calendar__menu" data-month-menu-panel></div>
      </div>
    </div>

    <div class="pcc-calendar__navrow">
      <div class="pcc-calendar__nav">
        <button type="button" class="pcc-btn pcc-btn--ghost" data-prev aria-label="Previous month">‹</button>
        <button type="button" class="pcc-btn pcc-btn--ghost" data-today>Today</button>
        <button type="button" class="pcc-btn pcc-btn--ghost" data-next aria-label="Next month">›</button>
      </div>

      <div class="pcc-calendar__month">
        <span data-month-label>January 2026</span>
        <span class="pcc-calendar__pill">Public times only</span>
      </div>
    </div>
  </div>

  <div class="pcc-calendar__gridwrap">
    <div class="pcc-calendar__dow">
      <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
    </div>

    <div class="pcc-calendar__grid" data-grid></div>
  </div>

  <!-- Popup -->
  <div class="pcc-modal" data-modal hidden>
    <div class="pcc-modal__backdrop" data-close></div>
    <div class="pcc-modal__card" role="dialog" aria-modal="true" aria-label="Event detail">
      <button class="pcc-modal__close" type="button" data-close aria-label="Close">×</button>

      <div class="pcc-modal__img">
        <img src="" alt="" data-modal-img />
      </div>

      <div class="pcc-modal__content">
        <div class="pcc-modal__when" data-modal-when></div>
        <div class="pcc-modal__title" data-modal-title></div>
        <div class="pcc-modal__meta" data-modal-meta></div>
        <div class="pcc-modal__desc" data-modal-desc></div>

        <div class="pcc-modal__actions">
          <a class="pcc-btn pcc-btn--primary" href="#" target="_blank" rel="noopener" data-modal-link>Detail</a>
        </div>
      </div>
    </div>
  </div>

</div>