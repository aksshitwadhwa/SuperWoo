<?php
defined('ABSPATH') || exit;
?>
<section class="superwoo-videos superwoo-videos--<?php echo esc_attr($layout); ?>" data-superwoo-videos data-nonce="<?php echo esc_attr($nonce); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-quick-buy="<?php echo !empty($quick_buy) ? '1' : '0'; ?>">
    <?php foreach ($videos as $video) : ?>
        <article class="superwoo-video-card" data-superwoo-video-id="<?php echo esc_attr($video['id']); ?>">
            <div class="superwoo-video-card__media">
                <?php if (in_array($video['source'], ['media', 'url'], true) && $video['url']) : ?>
                    <video playsinline preload="metadata" <?php echo $video['muted'] ? 'muted' : ''; ?> <?php echo $video['loop'] ? 'loop' : ''; ?> <?php echo $video['autoplay'] ? 'data-superwoo-autoplay' : ''; ?> poster="<?php echo esc_url($video['poster']); ?>">
                        <source src="<?php echo esc_url($video['url']); ?>">
                    </video>
                <?php else : ?>
                    <a class="superwoo-video-card__provider" href="<?php echo esc_url($video['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(sprintf(__('Open %s', 'superwoo'), $video['title'])); ?>" style="background-image:url('<?php echo esc_url($video['poster']); ?>')">&#9654;</a>
                <?php endif; ?>
                <button type="button" class="superwoo-video-card__play" data-superwoo-video-play aria-label="<?php esc_attr_e('Play or pause video', 'superwoo'); ?>">&#9654;</button>
                <?php if (!empty($fullscreen) && in_array($video['source'], ['media', 'url'], true) && $video['url']) : ?>
                    <button type="button" class="superwoo-video-card__expand" data-superwoo-open-viewer aria-label="<?php esc_attr_e('Open fullscreen video', 'superwoo'); ?>">&#10697;</button>
                <?php endif; ?>
                <?php if ($video['products']) : ?>
                    <button type="button" class="superwoo-video-card__products" data-superwoo-products><?php echo esc_html(sprintf(_n('%d product', '%d products', count($video['products']), 'superwoo'), count($video['products']))); ?></button>
                <?php endif; ?>
            </div>
            <h3 class="superwoo-video-card__title"><?php echo esc_html($video['title']); ?></h3>
            <?php if ($video['products']) : ?>
                <div class="superwoo-video-card__tray" hidden>
                    <div class="superwoo-video-card__tray-inner">
                        <?php foreach ($video['products'] as $item) : ?>
                            <?php
                            $product = wc_get_product(absint($item['product_id'] ?? 0));
                            if (!$product || !$product->is_visible()) {
                                continue;
                            }
                            $start = max(0, (float) ($item['start'] ?? 0));
                            $end   = max(0, (float) ($item['end'] ?? 0));
                            ?>
                            <article class="superwoo-video-product" data-product-id="<?php echo esc_attr($product->get_id()); ?>" data-superwoo-start="<?php echo esc_attr($start); ?>" data-superwoo-end="<?php echo esc_attr($end); ?>">
                                <a href="<?php echo esc_url($product->get_permalink()); ?>">
                                    <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </a>
                                <div>
                                    <a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a>
                                    <span><?php echo wp_kses_post($product->get_price_html()); ?></span>
                                    <?php if ($product->is_purchasable() && $product->is_in_stock()) : ?>
                                        <button type="button" class="button" data-superwoo-video-add data-product-id="<?php echo esc_attr($product->get_id()); ?>" data-product-type="<?php echo esc_attr($product->get_type()); ?>" data-video-id="<?php echo esc_attr($video['id']); ?>"><?php echo esc_html($video['cta']); ?></button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
