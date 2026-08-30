<?php
defined('ABSPATH') || exit;

$review_count = (int) $summary['count'];
$average = (string) $summary['average'];
$breakdown = isset($summary['breakdown']) && is_array($summary['breakdown']) ? $summary['breakdown'] : [];
$image_thumbs = isset($summary['image_thumbs']) && is_array($summary['image_thumbs']) ? $summary['image_thumbs'] : [];
?>
<section class="superwoo-reviews" data-superwoo-reviews>
    <div class="superwoo-reviews__top">
        <div class="superwoo-reviews__breakdown" aria-label="<?php esc_attr_e('Rating breakdown', 'superwoo'); ?>">
            <?php for ($star = 5; $star >= 1; $star--) : ?>
                <?php
                $count = isset($breakdown[$star]) ? (int) $breakdown[$star] : 0;
                $percent = $review_count > 0 ? min(100, ($count / $review_count) * 100) : 0;
                ?>
                <div class="superwoo-review-bar">
                    <span class="superwoo-review-bar__stars" aria-label="<?php echo esc_attr(sprintf(_n('%d star', '%d stars', $star, 'superwoo'), $star)); ?>">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span><?php echo $i <= $star ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                    </span>
                    <span class="superwoo-review-bar__track"><span style="width: <?php echo esc_attr($percent); ?>%;"></span></span>
                    <span class="superwoo-review-bar__count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                </div>
            <?php endfor; ?>
        </div>

        <div class="superwoo-reviews__summary">
            <span class="superwoo-reviews__summary-icon" aria-hidden="true">☷</span>
            <h2><?php esc_html_e('Customer Reviews', 'superwoo'); ?></h2>
            <p class="superwoo-reviews__summary-subtitle"><?php esc_html_e('Real experiences from our customers', 'superwoo'); ?></p>
            <div class="superwoo-reviews__score">
                <strong><?php echo esc_html($average); ?></strong>
                <span><?php echo esc_html(sprintf(_n('%s review', '%s reviews', $review_count, 'superwoo'), number_format_i18n($review_count))); ?></span>
            </div>
            <?php if (!empty($image_thumbs)) : ?>
                <div class="superwoo-reviews__thumbs" aria-label="<?php esc_attr_e('Review pictures', 'superwoo'); ?>">
                    <?php foreach ($image_thumbs as $index => $image) : ?>
                        <a href="<?php echo esc_url($image['full']); ?>" target="_blank" rel="noopener" class="superwoo-review-thumb">
                            <img src="<?php echo esc_url($image['src']); ?>" alt="<?php echo esc_attr($image['alt'] ? $image['alt'] : sprintf(__('Review picture %d', 'superwoo'), $index + 1)); ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="superwoo-reviews__action">
            <?php if (!empty($write_enabled)) : ?>
                <button type="button" class="superwoo-review-write" data-superwoo-write-review><?php esc_html_e('Write a review', 'superwoo'); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="superwoo-reviews__tools">
        <label class="superwoo-review-search">
            <span class="screen-reader-text"><?php esc_html_e('Search reviews', 'superwoo'); ?></span>
            <input type="search" data-superwoo-review-search placeholder="<?php esc_attr_e('Search', 'superwoo'); ?>">
        </label>
        <div class="superwoo-review-filters" data-superwoo-review-filters>
            <button type="button" class="superwoo-review-filter-toggle" data-superwoo-filter-toggle aria-expanded="false">
                <span aria-hidden="true">▽</span>
                <?php esc_html_e('Filters', 'superwoo'); ?>
            </button>
            <div class="superwoo-review-filter-menu" data-superwoo-filter-menu hidden aria-label="<?php esc_attr_e('Filter reviews by rating', 'superwoo'); ?>">
                <button type="button" data-superwoo-rating-filter="0" class="is-active"><?php esc_html_e('All ratings', 'superwoo'); ?></button>
                <?php for ($star = 5; $star >= 1; $star--) : ?>
                    <button type="button" data-superwoo-rating-filter="<?php echo esc_attr($star); ?>"><?php echo esc_html($star); ?> <?php echo esc_html(_n('star', 'stars', $star, 'superwoo')); ?></button>
                <?php endfor; ?>
            </div>
        </div>
        <label class="superwoo-review-sort">
            <span class="screen-reader-text"><?php esc_html_e('Sort reviews', 'superwoo'); ?></span>
            <select data-superwoo-review-sort>
                <option value="pictures"><?php esc_html_e('Media first', 'superwoo'); ?></option>
                <option value="newest"><?php esc_html_e('Newest first', 'superwoo'); ?></option>
                <option value="highest"><?php esc_html_e('Highest rating', 'superwoo'); ?></option>
                <option value="lowest"><?php esc_html_e('Lowest rating', 'superwoo'); ?></option>
            </select>
        </label>
    </div>

    <p class="superwoo-review-results" data-superwoo-review-results aria-live="polite"></p>

    <?php if (!empty($review_form)) : ?>
        <div class="superwoo-review-form-panel" data-superwoo-review-form hidden>
            <?php echo $review_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($reviews)) : ?>
        <div class="superwoo-review-grid" data-superwoo-review-grid>
            <?php foreach ($reviews as $index => $review) : ?>
                <?php
                $search_text = strtolower($review['author'] . ' ' . $review['title'] . ' ' . $review['content']);
                $has_images = !empty($review['images']);
                $has_videos = !empty($review['videos']);
                $has_media = $has_images || $has_videos;
                ?>
                <article
                    class="superwoo-review-card"
                    data-superwoo-review-card
                    data-rating="<?php echo esc_attr((int) $review['rating']); ?>"
                    data-date="<?php echo esc_attr((int) $review['timestamp']); ?>"
                    data-has-images="<?php echo esc_attr($has_media ? '1' : '0'); ?>"
                    data-has-media="<?php echo esc_attr($has_media ? '1' : '0'); ?>"
                    data-search="<?php echo esc_attr($search_text); ?>"
                    <?php echo $index >= 8 ? 'hidden' : ''; ?>
                >
                    <div class="superwoo-review-card__stars" aria-label="<?php echo esc_attr(sprintf(_n('%d star', '%d stars', (int) $review['rating'], 'superwoo'), (int) $review['rating'])); ?>">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span><?php echo $i <= (int) $review['rating'] ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                    </div>
                    <div class="superwoo-review-card__meta">
                        <strong><?php echo esc_html($review['author']); ?></strong>
                        <span><?php echo esc_html($review['date']); ?></span>
                    </div>
                    <?php if (!empty($review['verified'])) : ?>
                        <span class="superwoo-review-verified"><?php esc_html_e('Verified owner', 'superwoo'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($review['title'])) : ?>
                        <h3><?php echo esc_html($review['title']); ?></h3>
                    <?php endif; ?>
                    <?php if (!empty($review['content'])) : ?>
                        <p><?php echo esc_html($review['content']); ?></p>
                    <?php endif; ?>
                    <?php if ($has_images) : ?>
                        <div class="superwoo-review-card__images">
                            <?php foreach (array_slice($review['images'], 0, 4) as $image) : ?>
                                <a href="<?php echo esc_url($image['full']); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url($image['src']); ?>" alt="<?php echo esc_attr($image['alt'] ? $image['alt'] : __('Review picture', 'superwoo')); ?>">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($has_videos) : ?>
                        <div class="superwoo-review-card__videos">
                            <?php foreach (array_slice($review['videos'], 0, 2) as $video) : ?>
                                <video controls preload="metadata" playsinline>
                                    <source src="<?php echo esc_url($video['src']); ?>" <?php echo !empty($video['type']) ? 'type="' . esc_attr($video['type']) . '"' : ''; ?>>
                                    <?php esc_html_e('Your browser does not support this review video.', 'superwoo'); ?>
                                </video>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($reviews) > 8) : ?>
            <div class="superwoo-review-show-more-wrap" data-superwoo-show-more-wrap>
                <button type="button" class="superwoo-review-show-more" data-superwoo-show-more>
                    <span class="elementor-button-text"><?php esc_html_e('View All', 'superwoo'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="superwoo-review-empty">
            <h3><?php esc_html_e('No reviews yet', 'superwoo'); ?></h3>
            <p><?php esc_html_e('Be the first to share your experience with this product.', 'superwoo'); ?></p>
        </div>
    <?php endif; ?>
</section>
