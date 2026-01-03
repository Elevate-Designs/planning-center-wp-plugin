<?php
if (!defined('ABSPATH')) { exit; }

class PCC_Data {

  /** @var PCC_API */
  private $api;

  public function __construct(PCC_API $api) {
    $this->api = $api;
  }

  private function cache_bust() {
    $opt = get_option(PCC_OPTION_KEY, array());
    return isset($opt['cache_bust']) ? (int)$opt['cache_bust'] : 1;
  }

  private function cache_get($key) {
    return get_transient('pcc_' . $this->cache_bust() . '_' . $key);
  }

  private function cache_set($key, $value, $ttl = 300) {
    set_transient('pcc_' . $this->cache_bust() . '_' . $key, $value, $ttl);
  }

  /**
   * Fetch event instances (raw JSON:API) and normalize.
   * We DO NOT rely on "public_only" API filters (often causes empty).
   * We fetch more and filter in PHP safely.
   */
  public function get_upcoming_instances($limit = 50) {
    $limit = max(1, min(100, (int)$limit));

    $ck = 'event_instances_' . $limit;
    $cached = $this->cache_get($ck);
    if (is_array($cached)) return $cached;

    // Try a "sort/order" param (some APIs support it; if ignored, fine)
    $json = $this->api->get('/calendar/v2/event_instances', array(
      'per_page' => $limit,
      'include'  => 'event',
      'order'    => 'starts_at',
    ));

    // Fallback if API doesn't like `order`
    if (is_wp_error($json)) {
      $json = $this->api->get('/calendar/v2/event_instances', array(
        'per_page' => $limit,
        'include'  => 'event',
      ));
      if (is_wp_error($json)) return $json;
    }

    $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : array();
    $included = $this->index_included(isset($json['included']) ? $json['included'] : array());

    $out = array();
    foreach ($data as $inst) {
      $out[] = $this->normalize_instance($inst, $included);
    }

    // Filter: only future (>= now-1day) to match "upcoming" feel
    $now = time() - DAY_IN_SECONDS;
    $out = array_values(array_filter($out, function($it) use ($now) {
      $ts = !empty($it['starts_at']) ? strtotime($it['starts_at']) : 0;
      return $ts ? ($ts >= $now) : false;
    }));

    // Sort asc
    usort($out, function($a, $b) {
      return strtotime($a['starts_at']) <=> strtotime($b['starts_at']);
    });

    $this->cache_set($ck, $out, 300);
    return $out;
  }

  public function get_events_slider($max = 6, $public_only = false) {
    $max = max(1, min(50, (int)$max));

    $instances = $this->get_upcoming_instances(80);
    if (is_wp_error($instances)) return $instances;

    if ($public_only) {
      $instances = $this->filter_public_only($instances);
    }

    $instances = array_slice($instances, 0, $max);

    // Ensure image_url (Church Center) with per-event lookup fallback
    foreach ($instances as &$it) {
      if (empty($it['image_url']) && !empty($it['event_id'])) {
        $it['image_url'] = $this->get_event_image_from_event($it['event_id']);
      }
    }
    unset($it);

    return $instances;
  }

  public function get_calendar_instances($months = 2, $public_only = true) {
    $months = max(1, min(12, (int)$months));

    // Date range: start of current month → end of (months) month grid
    $tz = wp_timezone();
    $start = new DateTime('first day of this month 00:00:00', $tz);
    $end = (clone $start)->modify('+' . $months . ' months')->modify('-1 second');

    $ck = 'calendar_' . $start->format('Ymd') . '_' . $months . '_' . ($public_only ? '1' : '0');
    $cached = $this->cache_get($ck);
    if (is_array($cached)) return $cached;

    // Pull a decent amount for 2 months (recurring Sundays etc)
    $instances = $this->get_upcoming_instances(100);
    if (is_wp_error($instances)) return $instances;

    $start_ts = $start->getTimestamp();
    $end_ts   = $end->getTimestamp();

    $instances = array_values(array_filter($instances, function($it) use ($start_ts, $end_ts) {
      $ts = !empty($it['starts_at']) ? strtotime($it['starts_at']) : 0;
      return $ts && $ts >= $start_ts && $ts <= $end_ts;
    }));

    if ($public_only) {
      $instances = $this->filter_public_only($instances);
    }

    // Ensure images
    foreach ($instances as &$it) {
      if (empty($it['image_url']) && !empty($it['event_id'])) {
        $it['image_url'] = $this->get_event_image_from_event($it['event_id']);
      }
    }
    unset($it);

    $model = $this->build_calendar_model($instances, $months);

    $this->cache_set($ck, $model, 300);
    return $model;
  }

  private function filter_public_only($instances) {
    // IMPORTANT: Do not over-filter. Only filter when we can detect fields.
    return array_values(array_filter($instances, function($it) {
      // time_type: public/private
      if (isset($it['time_type']) && $it['time_type'] !== '') {
        if (strtolower($it['time_type']) !== 'public') return false;
      }
      // visible_in_church_center or published_to_church_center
      if (isset($it['visible_in_church_center'])) {
        if (!$it['visible_in_church_center']) return false;
      }
      if (isset($it['published_to_church_center'])) {
        if (!$it['published_to_church_center']) return false;
      }
      return true;
    }));
  }

  private function index_included($included) {
    $map = array();
    if (!is_array($included)) return $map;
    foreach ($included as $inc) {
      if (!is_array($inc)) continue;
      $type = isset($inc['type']) ? strtolower((string)$inc['type']) : '';
      $id   = isset($inc['id']) ? (string)$inc['id'] : '';
      if ($type && $id) {
        if (!isset($map[$type])) $map[$type] = array();
        $map[$type][$id] = $inc;
      }
    }
    return $map;
  }

  private function normalize_instance($inst, $included) {
    $attrs = isset($inst['attributes']) && is_array($inst['attributes']) ? $inst['attributes'] : array();

    $event_id = '';
    if (!empty($inst['relationships']['event']['data']['id'])) {
      $event_id = (string)$inst['relationships']['event']['data']['id'];
    }

    $event = null;
    if ($event_id) {
      // Type might be "Event" or "events" depending on API, so try both keys
      if (!empty($included['event'][$event_id])) $event = $included['event'][$event_id];
      if (!$event && !empty($included['events'][$event_id])) $event = $included['events'][$event_id];
    }

    $eattrs = $event && !empty($event['attributes']) && is_array($event['attributes']) ? $event['attributes'] : array();

    $title = $eattrs['name'] ?? $eattrs['title'] ?? $attrs['name'] ?? $attrs['title'] ?? '';
    $desc  = $eattrs['description'] ?? $eattrs['summary'] ?? $attrs['description'] ?? '';
    $url   = $eattrs['church_center_url'] ?? $eattrs['public_url'] ?? $eattrs['url'] ?? '';

    $starts = $attrs['starts_at'] ?? $attrs['start'] ?? '';
    $ends   = $attrs['ends_at'] ?? $attrs['end'] ?? '';

    $img = $eattrs['image_url'] ?? '';
    if (is_array($img)) {
      // sometimes image_url is object/array
      $img = $img['original'] ?? $img['url'] ?? '';
    }

    $location = $attrs['location'] ?? $eattrs['location'] ?? '';
    $address  = $attrs['address'] ?? $eattrs['address'] ?? '';
    $time_type = $attrs['time_type'] ?? '';
    $visible_cc = $attrs['visible_in_church_center'] ?? null;
    $published_cc = $attrs['published_to_church_center'] ?? null;

    return array(
      'id' => isset($inst['id']) ? (string)$inst['id'] : '',
      'event_id' => $event_id,
      'title' => (string)$title,
      'description' => (string)$desc,
      'url' => (string)$url,
      'starts_at' => (string)$starts,
      'ends_at' => (string)$ends,
      'image_url' => (string)$img,
      'location' => (string)$location,
      'address' => (string)$address,
      'time_type' => (string)$time_type,
      'visible_in_church_center' => is_null($visible_cc) ? null : (bool)$visible_cc,
      'published_to_church_center' => is_null($published_cc) ? null : (bool)$published_cc,
    );
  }

  private function get_event_image_from_event($event_id) {
    $event_id = (string)$event_id;
    if ($event_id === '') return '';

    $ck = 'event_img_' . $event_id;
    $cached = $this->cache_get($ck);
    if (is_string($cached)) return $cached;

    $json = $this->api->get('/calendar/v2/events/' . rawurlencode($event_id), array(
      'include' => 'event_image'
    ));

    if (is_wp_error($json)) {
      // don't cache hard failures; just return empty
      return '';
    }

    $img = '';
    if (!empty($json['data']['attributes']['image_url'])) {
      $img = $json['data']['attributes']['image_url'];
      if (is_array($img)) $img = $img['original'] ?? $img['url'] ?? '';
    }

    // Try included event_image
    if ($img === '' && !empty($json['included']) && is_array($json['included'])) {
      foreach ($json['included'] as $inc) {
        if (empty($inc['attributes']) || !is_array($inc['attributes'])) continue;
        $t = isset($inc['type']) ? strtolower((string)$inc['type']) : '';
        if ($t === 'event_image' || $t === 'eventimage' || $t === 'image') {
          $a = $inc['attributes'];
          $img = $a['url'] ?? $a['original'] ?? $a['image_url'] ?? '';
          if (is_array($img)) $img = $img['original'] ?? $img['url'] ?? '';
          if ($img) break;
        }
      }
    }

    $this->cache_set($ck, (string)$img, 3600);
    return (string)$img;
  }

  private function build_calendar_model($instances, $months) {
    $tz = wp_timezone();
    $start = new DateTime('first day of this month 00:00:00', $tz);

    // index by date
    $by_date = array();
    foreach ($instances as $it) {
      $ts = strtotime($it['starts_at']);
      if (!$ts) continue;
      $key = wp_date('Y-m-d', $ts);
      if (!isset($by_date[$key])) $by_date[$key] = array();
      $by_date[$key][] = $it;
    }

    $out_months = array();

    for ($i = 0; $i < $months; $i++) {
      $mStart = (clone $start)->modify('+' . $i . ' months');
      $label = $mStart->format('F Y');

      $firstDay = (clone $mStart);
      $lastDay  = (clone $mStart)->modify('last day of this month');

      // grid start: Sunday before or same day
      $gridStart = (clone $firstDay);
      $dow = (int)$gridStart->format('w'); // 0=Sun
      if ($dow !== 0) $gridStart->modify('-' . $dow . ' days');

      // grid end: Saturday after or same day
      $gridEnd = (clone $lastDay);
      $dow2 = (int)$gridEnd->format('w');
      if ($dow2 !== 6) $gridEnd->modify('+' . (6 - $dow2) . ' days');

      $weeks = array();
      $cur = (clone $gridStart);

      while ($cur <= $gridEnd) {
        $week = array();
        for ($d = 0; $d < 7; $d++) {
          $dateKey = $cur->format('Y-m-d');
          $inMonth = ($cur->format('Y-m') === $mStart->format('Y-m'));
          $events  = isset($by_date[$dateKey]) ? $by_date[$dateKey] : array();

          $week[] = array(
            'date' => $dateKey,
            'day' => (int)$cur->format('j'),
            'in_month' => $inMonth,
            'events' => $events,
          );

          $cur->modify('+1 day');
        }
        $weeks[] = $week;
      }

      $out_months[] = array(
        'label' => $label,
        'year' => (int)$mStart->format('Y'),
        'month' => (int)$mStart->format('n'),
        'weeks' => $weeks,
      );
    }

    return array(
      'months' => $out_months,
    );
  }
}