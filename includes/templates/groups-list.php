<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $items */

?>
<div class="pcc pcc-groups">
    <ul class="pcc-list">
        <?php foreach ($items as $it) :
            $title = isset($it['title']) ? $it['title'] : '';
            $url   = isset($it['url']) ? $it['url'] : '';
        ?>
            <li class="pcc-item pcc-group">
                <div class="pcc-item__title">
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($title); ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
