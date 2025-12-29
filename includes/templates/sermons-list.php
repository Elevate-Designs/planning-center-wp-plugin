<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */

?>
<div class="pcc pcc-sermons">
    <ul class="pcc-list">
        <?php foreach ($items as $it) :
            $title = isset($it['title']) ? $it['title'] : '';
            $url   = isset($it['url']) ? $it['url'] : '';
            $date  = isset($it['date']) ? $it['date'] : '';

            $ts = $date ? strtotime($date) : 0;
            $date_str = $ts ? wp_date(get_option('date_format'), $ts) : '';
        ?>
            <li class="pcc-item pcc-sermon">
                <div class="pcc-item__title">
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($title); ?>
                    <?php endif; ?>
                </div>
                <?php if ($date_str) : ?>
                    <div class="pcc-item__meta"><?php echo esc_html($date_str); ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
