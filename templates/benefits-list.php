<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-benefits-list">
    <?php foreach ($terms as $term) : ?>
        <?php
        $icon_id = get_term_meta($term->term_id, 'benefit_icon', true);
        $icon_url = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : '';
        ?>
        <div class="superwoo-benefit-item">
            <?php if ($icon_url) : ?>
                <span class="superwoo-benefit-icon"><img src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($term->name); ?>"></span>
            <?php endif; ?>
            <span class="superwoo-benefit-text"><?php echo esc_html($term->name); ?></span>
        </div>
    <?php endforeach; ?>
</div>
