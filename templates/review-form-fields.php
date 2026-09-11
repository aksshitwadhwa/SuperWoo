<?php
defined('ABSPATH') || exit;
$rating_id = wp_unique_id('superwoo-rating-');
$media_id = wp_unique_id('superwoo-media-');
?>
<?php if (wc_review_ratings_enabled()) : ?>
    <fieldset class="superwoo-review-rating-field">
        <legend><?php esc_html_e('Your rating', 'superwoo'); ?> <?php if (wc_review_ratings_required()) : ?><span class="required">*</span><?php endif; ?></legend>
        <div class="superwoo-review-rating-controls">
            <div class="superwoo-review-rating-stars" data-superwoo-rating-stars>
                <?php for ($star = 1; $star <= 5; $star++) : ?>
                    <label class="superwoo-review-rating-star" for="<?php echo esc_attr($rating_id . $star); ?>">
                        <input type="radio" name="rating" id="<?php echo esc_attr($rating_id . $star); ?>" value="<?php echo esc_attr($star); ?>" <?php echo wc_review_ratings_required() ? 'required' : ''; ?> aria-label="<?php echo esc_attr(sprintf(_n('%d star', '%d stars', $star, 'superwoo'), $star)); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.3L5.8 21 7 14.2 2 9.3l6.9-1Z"/></svg>
                    </label>
                <?php endfor; ?>
            </div>
            <span class="superwoo-review-rating-hint" data-superwoo-rating-hint aria-live="polite"><?php esc_html_e('Click on a star to rate', 'superwoo'); ?></span>
        </div>
    </fieldset>
<?php endif; ?>
<p class="comment-form-comment">
    <label for="comment"><?php esc_html_e('Your review', 'superwoo'); ?> <span class="required">*</span></label>
    <textarea id="comment" name="comment" rows="6" maxlength="1000" required placeholder="<?php esc_attr_e('Share your experience with this product...', 'superwoo'); ?>" data-superwoo-review-text></textarea>
    <span class="superwoo-review-character-count" data-superwoo-review-count>0/1000</span>
</p>
<?php echo wp_nonce_field('superwoo_review_images_' . $product->get_id(), 'superwoo_review_images_nonce', true, false); ?>
<div class="superwoo-review-upload-panel">
    <div class="superwoo-review-upload-inner">
        <svg class="superwoo-review-upload-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><rect x="8" y="6" width="42" height="42" rx="7"/><circle cx="20" cy="18" r="3"/><path d="m9 40 13-13 10 9 9-11 9 9"/><circle cx="48" cy="47" r="14" class="superwoo-review-upload-plus"/><path d="M48 39v16m-8-8h16" stroke="white"/></svg>
        <label class="superwoo-review-upload-title" for="<?php echo esc_attr($media_id); ?>"><?php esc_html_e('Upload product photos or videos', 'superwoo'); ?></label>
        <p><?php esc_html_e('Show us your product in action! Add photos or videos to make your review more helpful.', 'superwoo'); ?></p>
        <label class="superwoo-review-file-button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 14v7h16v-7"/></svg>
            <span><?php esc_html_e('Choose files', 'superwoo'); ?></span>
            <input id="<?php echo esc_attr($media_id); ?>" name="superwoo_review_media[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/ogg" multiple data-superwoo-review-media>
        </label>
        <span class="superwoo-review-upload-help" data-superwoo-review-media-help aria-live="polite"><?php esc_html_e('Optional. Upload up to 4 photos and 2 videos.', 'superwoo'); ?></span>
        <small><?php esc_html_e('Photos: JPG, PNG, WebP, GIF (max 5 MB each). Videos: MP4, WebM, MOV, OGG (max 50 MB each).', 'superwoo'); ?></small>
    </div>
</div>
<aside class="superwoo-review-tips">
    <h3><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 16c0-3-2-3-2-7a6 6 0 0 1 12 0c0 4-2 4-2 7ZM8 19h8m-6 3h4M12 0v1M2 3l2 2M0 10h3m18 0h3m-2-7-2 2"/></svg> <?php esc_html_e('Tips for a great review', 'superwoo'); ?></h3>
    <ul>
        <li><?php esc_html_e('Share what you liked (or didn’t like)', 'superwoo'); ?></li>
        <li><?php esc_html_e('Mention how long you’ve used the product', 'superwoo'); ?></li>
        <li><?php esc_html_e('Include photos or videos (if possible)', 'superwoo'); ?></li>
        <li><?php esc_html_e('Be honest and helpful to other customers', 'superwoo'); ?></li>
    </ul>
</aside>
