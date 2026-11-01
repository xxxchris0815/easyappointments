<?php if (filter_var(setting('custom_css_enabled', '0'), FILTER_VALIDATE_BOOLEAN)): ?>
    <?php
    $custom_css = trim((string) setting('custom_css', ''));
    $cache_bust = substr(sha1($custom_css), 0, 12);
    ?>
    <?php if ($custom_css !== ''): ?>
        <link rel="stylesheet" type="text/css"
              href="<?= site_url('custom_css') ?>?v=<?= e($cache_bust) ?>"
              id="ea-custom-css-link">
    <?php endif; ?>
<?php endif; ?>
