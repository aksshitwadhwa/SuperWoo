<?php
defined('ABSPATH') || exit;

if ($next_tier) :
    $next_min_qty = (int) ($next_tier['min_qty'] ?? $next_tier['qty']);
    $remaining = max(0, $next_min_qty - (int) $qty);
    $progress = max(0, min(100, ((int) $qty / $next_min_qty) * 100));
    $plural = $remaining > 1 ? 's' : '';
    $next_free_product = !empty($next_tier['free_product_id']) ? wc_get_product(absint($next_tier['free_product_id'])) : null;
    $next_reward = [];
    if (!empty($next_tier['discount'])) {
        /* translators: %s: discount percentage. */
        $next_reward[] = sprintf(esc_html__('%1$s%% off', 'superwoo'), esc_html($next_tier['discount']));
    }
    if ($next_free_product) {
        /* translators: %s: free product name. */
        $next_reward[] = sprintf(esc_html__('<strong>Free</strong> %1$s', 'superwoo'), esc_html($next_free_product->get_name()));
    }
    $next_reward_text = implode(' + ', $next_reward);
    ?>
    <div class="superwoo-bundle-notice">
        <div class="superwoo-bundle-notice__top">
            <div>
                <?php
                printf(
                    esc_html__('Add %1$s more %2$s%3$s to unlock %4$s.', 'superwoo'),
                    esc_html($remaining),
                    esc_html($scope_label),
                    esc_html($plural),
                    esc_html($next_reward_text)
                );
                ?>
            </div>
            <?php /* translators: 1: quantity threshold, 2: reward description. */ ?>
            <strong><?php echo esc_html(sprintf(__('From %1$s: %2$s', 'superwoo'), $next_min_qty, $next_reward_text)); ?></strong>
        </div>
        <div class="superwoo-bundle-progress">
            <span class="superwoo-bundle-progress__fill" style="width:<?php echo esc_attr($progress); ?>%;"></span>
            <div class="superwoo-bundle-progress__labels">
                <span class="superwoo-bundle-progress__current"><?php echo esc_html((int) $qty); ?></span>
                <span class="superwoo-bundle-progress__separator">/</span>
                <span class="superwoo-bundle-progress__target"><?php echo esc_html($next_min_qty); ?></span>
            </div>
        </div>
    </div>
<?php elseif ($current_tier) : ?>
    <?php
    $current_free_product = !empty($current_tier['free_product_id']) ? wc_get_product(absint($current_tier['free_product_id'])) : null;
    $current_reward = [];
    if (!empty($current_tier['discount'])) {
        /* translators: %s: discount percentage. */
        $current_reward[] = sprintf(esc_html__('%1$s%% off', 'superwoo'), esc_html($current_tier['discount']));
    }
    if ($current_free_product) {
        /* translators: %s: free product name. */
        $current_reward[] = sprintf(esc_html__('<strong>Free</strong> %1$s', 'superwoo'), esc_html($current_free_product->get_name()));
    }
    ?>
    <div class="superwoo-bundle-notice superwoo-bundle-notice--success">
        <?php
        printf(
            esc_html__('You unlocked %s.', 'superwoo'),
            esc_html(implode(' + ', $current_reward))
        );
        ?>
    </div>
<?php endif; ?>
