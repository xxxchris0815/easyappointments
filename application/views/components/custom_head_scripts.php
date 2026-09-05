<?php
/**
 * Inject admin-controlled custom head scripts/HTML into the booking layout.
 *
 * Trust model: only authenticated administrators can set `custom_head_scripts`
 * via settings. The raw value is echoed intentionally (same approach as analytics
 * snippets). Do not expose this setting to non-admin roles.
 */
?>

<?php $custom_head_scripts = setting('custom_head_scripts'); ?>
<?php if (!empty($custom_head_scripts)): ?>
    <?= $custom_head_scripts ?>
<?php endif; ?>
