<?php defined('ABSPATH') || exit; ?>
<div id="superwoo-faq-wrap">
    <div id="superwoo-faq-rows">
        <?php foreach ($faqs as $i => $faq) : ?>
            <?php
            $question = isset($faq['question']) ? $faq['question'] : '';
            $answer = isset($faq['answer']) ? $faq['answer'] : '';
            include SUPERWOO_PATH . 'admin/views/faq-row.php';
            ?>
        <?php endforeach; ?>
    </div>
    <p><button type="button" class="button button-primary" id="superwoo-add-faq-row"><?php esc_html_e('+ Add FAQ', 'superwoo'); ?></button></p>
</div>

<script type="text/template" id="superwoo-faq-row-template">
    <?php
    $i = '__INDEX__';
    $question = '';
    $answer = '';
    include SUPERWOO_PATH . 'admin/views/faq-row.php';
    ?>
</script>
