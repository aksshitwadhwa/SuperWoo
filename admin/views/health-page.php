<?php defined('ABSPATH') || exit; ?>
<div class="wrap superwoo-admin-page">
    <h1><?php esc_html_e('SuperWoo Health', 'superwoo'); ?></h1>
    <p><?php esc_html_e('Safe diagnostics for plugin health, blank-screen troubleshooting, updater configuration, and cart pricing. No customer or payment details are shown.', 'superwoo'); ?></p>
    <?php foreach ($report as $section => $checks) : ?>
        <div class="superwoo-health-section">
            <h2><?php echo esc_html(ucwords(str_replace('_', ' ', $section))); ?></h2>
            <div class="superwoo-health-grid">
                <?php foreach ($checks as $check) : ?>
                    <div class="superwoo-health-card superwoo-health-card--<?php echo esc_attr($check['status']); ?>">
                        <strong><?php echo esc_html($check['label']); ?></strong>
                        <span><?php echo wp_kses_post($check['value']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <p class="description"><?php esc_html_e('Copy these results and the recent entries from SuperWoo → Logs when requesting support. If a blank screen returns, check wp-content/superwoo-fatal.log.', 'superwoo'); ?></p>
</div>
