<?php
defined('ABSPATH') || exit;

$review_count = (int) $summary['count'];
$average = (string) $summary['average'];
$breakdown = isset($summary['breakdown']) && is_array($summary['breakdown']) ? $summary['breakdown'] : [];
$stars_percent = min(100, max(0, (float) $average * 20));
$icon = static function ($name) {
    $paths = [
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>',
        'pen' => '<path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15Z M13 20h7"/>',
        'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/>',
        'people' => '<circle cx="12" cy="8" r="3"/><path d="M6 21v-3a6 6 0 0 1 12 0v3ZM5 5a4 4 0 0 0 0 8m14-8a4 4 0 0 1 0 8M3 15l-1 5m19-5 1 5"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'chat' => '<path d="M21 11.5a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.3-4.9a9 9 0 1 1 16.7-4.6Z"/><path d="M8 11h.1m3.9 0h.1m3.9 0h.1"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'filter' => '<path d="M3 4h18l-7 8v8l-4-2v-6Z"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'refresh' => '<path d="M20 8a8 8 0 0 0-14-3L3 8m0-5v5h5M4 16a8 8 0 0 0 14 3l3-3m0 5v-5h-5"/>',
        'check' => '<path fill="currentColor" stroke="none" d="m12 1 3 3 4-.2.2 4 3 3-3 3-.2 4-4 .2-3 3-3-3-4 .2-.2-4-3-3 3-3 .2-4 4-.2Z"/><path stroke="white" d="m8 11 3 3 5-5"/>',
    ];
    return '<svg class="superwoo-review-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};
?>
<section class="superwoo-reviews" data-superwoo-reviews data-product-id="<?php echo esc_attr($product->get_id()); ?>">
    <div class="superwoo-review-notice" data-superwoo-review-notice role="status" tabindex="-1" hidden>
        <strong><?php esc_html_e('Thank you for your review!', 'superwoo'); ?></strong>
        <span><?php esc_html_e('Your review has been submitted successfully.', 'superwoo'); ?></span>
        <span data-superwoo-review-pending hidden><?php esc_html_e('It will appear once it has been approved.', 'superwoo'); ?></span>
    </div>
    <div class="superwoo-reviews__top">
        <div class="superwoo-reviews__intro">
            <span class="superwoo-reviews__eyebrow"><?php echo $icon('star'); ?> <?php esc_html_e('Customer Reviews', 'superwoo'); ?></span>
            <h2><?php esc_html_e('Real People.', 'superwoo'); ?><br><?php esc_html_e('Real', 'superwoo'); ?> <span><?php esc_html_e('Results.', 'superwoo'); ?></span></h2>
            <p><?php esc_html_e('See what our customers are saying about their experience. Honest reviews from real users.', 'superwoo'); ?></p>
            <?php if ($review_count) : ?>
                <div class="superwoo-reviews__community">
                    <div class="superwoo-reviews__avatars" aria-hidden="true">
                        <?php foreach (array_slice($reviews, 0, 4) as $review) : ?>
                            <img src="<?php echo esc_url($review['avatar']); ?>" alt="" width="48" height="48" loading="lazy">
                        <?php endforeach; ?>
                        <span>+<?php echo esc_html(number_format_i18n($review_count)); ?></span>
                    </div>
                    <span><?php esc_html_e('Shared by', 'superwoo'); ?><br><?php esc_html_e('our customer community', 'superwoo'); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="superwoo-reviews__score-panel">
            <div class="superwoo-reviews__rating-summary">
                <strong class="superwoo-reviews__average"><?php echo esc_html($average); ?></strong>
                <span class="superwoo-reviews__stars" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'superwoo'), $average)); ?>"><span aria-hidden="true">★★★★★</span><span aria-hidden="true" style="width: <?php echo esc_attr($stars_percent); ?>%">★★★★★</span></span>
                <span class="superwoo-reviews__based-on"><?php echo esc_html(sprintf(_n('Based on %s review', 'Based on %s reviews', $review_count, 'superwoo'), number_format_i18n($review_count))); ?></span>
            </div>
            <div class="superwoo-reviews__breakdown" aria-label="<?php esc_attr_e('Rating breakdown', 'superwoo'); ?>">
                <?php for ($star = 5; $star >= 1; $star--) : ?>
                    <?php $count = (int) ($breakdown[$star] ?? 0); $percent = $review_count ? min(100, $count / $review_count * 100) : 0; ?>
                    <div class="superwoo-review-bar">
                        <span class="superwoo-review-bar__label"><?php echo esc_html($star); ?> <span aria-hidden="true">★</span><span class="screen-reader-text"><?php esc_html_e('stars', 'superwoo'); ?></span></span>
                        <span class="superwoo-review-bar__track"><span style="width: <?php echo esc_attr($percent); ?>%;"></span></span>
                        <span class="superwoo-review-bar__count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="superwoo-reviews__summary">
            <?php if (!empty($write_enabled)) : ?>
                <button type="button" class="superwoo-review-write" data-superwoo-write-review aria-expanded="false"><?php echo $icon('pen'); ?> <?php esc_html_e('Write A Review', 'superwoo'); ?> <?php echo $icon('arrow'); ?></button>
            <?php endif; ?>
            <p class="superwoo-reviews__summary-subtitle"><?php esc_html_e('Share your experience and help others make the right choice.', 'superwoo'); ?></p>
            <div class="superwoo-reviews__trust">
                <span><?php echo $icon('shield'); ?><span><?php esc_html_e('Verified', 'superwoo'); ?><br><?php esc_html_e('Reviews', 'superwoo'); ?></span></span>
                <span><?php echo $icon('people'); ?><span><?php esc_html_e('Real', 'superwoo'); ?><br><?php esc_html_e('Customers', 'superwoo'); ?></span></span>
                <span><?php echo $icon('heart'); ?><span><?php esc_html_e('Honest', 'superwoo'); ?><br><?php esc_html_e('Feedback', 'superwoo'); ?></span></span>
            </div>
        </div>
    </div>

    <div class="superwoo-reviews__tools">
        <label class="superwoo-review-search">
            <?php echo $icon('search'); ?>
            <span class="screen-reader-text"><?php esc_html_e('Search reviews', 'superwoo'); ?></span>
            <input type="search" data-superwoo-review-search placeholder="<?php esc_attr_e('Search reviews by name, keyword or topic...', 'superwoo'); ?>">
        </label>
        <div class="superwoo-review-filters" data-superwoo-review-filters>
            <button type="button" class="superwoo-review-filter-toggle" data-superwoo-filter-toggle aria-expanded="false">
                <?php echo $icon('filter'); ?><span data-superwoo-filter-label data-default-label="<?php esc_attr_e('Filters', 'superwoo'); ?>"><?php esc_html_e('Filters', 'superwoo'); ?></span><?php echo $icon('chevron'); ?>
            </button>
            <div class="superwoo-review-filter-menu" data-superwoo-filter-menu hidden aria-label="<?php esc_attr_e('Filter reviews', 'superwoo'); ?>">
                <button type="button" data-superwoo-rating-filter="0" class="is-active" aria-pressed="true"><?php esc_html_e('All ratings', 'superwoo'); ?></button>
                <?php for ($star = 5; $star >= 1; $star--) : ?>
                    <button type="button" data-superwoo-rating-filter="<?php echo esc_attr($star); ?>" aria-pressed="false"><?php echo esc_html($star); ?> <?php echo esc_html(_n('star', 'stars', $star, 'superwoo')); ?></button>
                <?php endfor; ?>
                <label class="superwoo-review-media-select">
                    <span><?php esc_html_e('Media', 'superwoo'); ?></span>
                    <select data-superwoo-review-media-filter>
                        <option value="all"><?php esc_html_e('All reviews', 'superwoo'); ?></option>
                        <option value="media"><?php esc_html_e('Photos and videos', 'superwoo'); ?></option>
                        <option value="photos"><?php esc_html_e('Photos only', 'superwoo'); ?></option>
                    </select>
                </label>
            </div>
        </div>
        <label class="superwoo-review-sort">
            <span class="screen-reader-text"><?php esc_html_e('Sort reviews', 'superwoo'); ?></span>
            <select data-superwoo-review-sort>
                <option value="newest"><?php esc_html_e('Most recent', 'superwoo'); ?></option>
                <option value="highest"><?php esc_html_e('Highest rating', 'superwoo'); ?></option>
                <option value="lowest"><?php esc_html_e('Lowest rating', 'superwoo'); ?></option>
                <option value="pictures"><?php esc_html_e('Media first', 'superwoo'); ?></option>
            </select>
            <?php echo $icon('chevron'); ?>
        </label>
    </div>
    <p class="superwoo-review-results screen-reader-text" data-superwoo-review-results aria-live="polite"></p>
    <p class="superwoo-review-no-matches" data-superwoo-review-no-matches hidden><?php esc_html_e('No reviews match your search or filters.', 'superwoo'); ?></p>

    <?php if (!empty($review_form)) : ?>
        <dialog class="superwoo-review-form-panel" data-superwoo-review-form aria-label="<?php esc_attr_e('Write a Review', 'superwoo'); ?>" hidden>
            <button type="button" class="superwoo-review-modal-close" data-superwoo-review-close aria-label="<?php esc_attr_e('Close review form', 'superwoo'); ?>">×</button>
            <header class="superwoo-review-modal-header">
                <div>
                    <span class="superwoo-review-modal-eyebrow"><?php esc_html_e('Share your experience', 'superwoo'); ?></span>
                    <h2><?php esc_html_e('Write a Review', 'superwoo'); ?></h2>
                    <p><?php esc_html_e('Your feedback helps us and other customers make better choices.', 'superwoo'); ?></p>
                </div>
                <div class="superwoo-reviews__trust">
                    <span><?php echo $icon('chat'); ?><span><?php esc_html_e('Honest Reviews', 'superwoo'); ?></span></span>
                    <span><?php echo $icon('people'); ?><span><?php esc_html_e('Help Others', 'superwoo'); ?></span></span>
                    <span><?php echo $icon('heart'); ?><span><?php esc_html_e('Support Our Brand', 'superwoo'); ?></span></span>
                </div>
            </header>
            <?php echo $review_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </dialog>
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
                    data-has-images="<?php echo esc_attr($has_images ? '1' : '0'); ?>"
                    data-has-media="<?php echo esc_attr($has_media ? '1' : '0'); ?>"
                    data-search="<?php echo esc_attr($search_text); ?>"
                    <?php echo $index >= 3 ? 'hidden' : ''; ?>
                >
                    <div class="superwoo-review-card__header">
                        <img class="superwoo-review-card__avatar" src="<?php echo esc_url($review['avatar']); ?>" alt="" width="70" height="70" loading="lazy">
                        <div class="superwoo-review-card__meta">
                            <strong><?php echo esc_html($review['author']); ?>
                                <?php if (!empty($review['verified'])) : ?><span class="superwoo-review-verified" title="<?php esc_attr_e('Verified purchase', 'superwoo'); ?>"><?php echo $icon('check'); ?><span class="screen-reader-text"><?php esc_html_e('Verified purchase', 'superwoo'); ?></span></span><?php endif; ?>
                            </strong>
                            <span><?php echo esc_html($review['date']); ?></span>
                        </div>
                        <span class="superwoo-review-card__quote" aria-hidden="true">”</span>
                    </div>
                    <div class="superwoo-review-card__rating">
                        <span class="superwoo-review-card__stars" role="img" aria-label="<?php echo esc_attr(sprintf(__('%d out of 5 stars', 'superwoo'), (int) $review['rating'])); ?>">
                            <?php for ($i = 1; $i <= 5; $i++) : ?><span aria-hidden="true"<?php echo $i > (int) $review['rating'] ? ' class="is-empty"' : ''; ?>>★</span><?php endfor; ?>
                        </span>
                    </div>
                    <?php if (!empty($review['title']) && 0 !== strcasecmp(trim($review['title']), trim($review['content']))) : ?><h3><?php echo esc_html($review['title']); ?></h3><?php endif; ?>
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
                    <div class="superwoo-review-card__footer"><span class="superwoo-review-card__tag">
                        <?php echo $icon(!empty($review['verified']) ? 'shield' : 'heart'); ?>
                        <?php echo esc_html(!empty($review['verified']) ? __('Verified Purchase', 'superwoo') : __('Customer Review', 'superwoo')); ?>
                    </span></div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($reviews) > 3) : ?>
            <div class="superwoo-review-show-more-wrap" data-superwoo-show-more-wrap>
                <button type="button" class="superwoo-review-show-more" data-superwoo-show-more>
                    <span class="elementor-button-text"><?php esc_html_e('Load More Reviews', 'superwoo'); ?></span><?php echo $icon('refresh'); ?>
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
