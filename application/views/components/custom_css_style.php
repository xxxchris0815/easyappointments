<?php if (filter_var(setting('custom_css_enabled', '0'), FILTER_VALIDATE_BOOLEAN)): ?>
    <?php $custom_css = trim((string) setting('custom_css', '')); ?>
    <?php if ($custom_css !== ''): ?>
        <style id="ea-custom-css">
            <?= $custom_css ?>
        </style>
    <?php endif; ?>
<?php endif; ?>
