<?php extend('layouts/message_layout'); ?>

<?php section('content'); ?>

<?php
$end_title = trim((string) vars('booking_end_screen_title'));
$end_message = trim((string) vars('booking_end_screen_message'));
$show_details = filter_var(vars('booking_end_screen_show_details'), FILTER_VALIDATE_BOOLEAN);
?>

<div class="d-flex align-items-center justify-content-center min-vh-100-">
    <div class="text-center py-4 px-3">
        <div class="d-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mx-auto mb-4" style="width: 100px; height: 100px;">
            <i class="fas fa-calendar-check fa-3x text-success"></i>
        </div>

        <h3 class="text-success fw-semibold mb-4">
            <?= e($end_title !== '' ? $end_title : lang('appointment_registered')) ?>
        </h3>

        <?php if ($end_message !== ''): ?>
            <div class="fs-5 text-muted mb-4">
                <?= $end_message ?>
            </div>
        <?php else: ?>
            <p class="fs-5 text-muted mb-1">
                <?= lang('appointment_details_was_sent_to_you') ?>
            </p>

            <p class="text-muted small mb-4">
                <?= lang('check_spam_folder') ?>
            </p>
        <?php endif; ?>

        <?php if ($show_details && vars('appointment')): ?>
            <?php $appointment = vars('appointment'); ?>
            <?php $service = vars('service'); ?>
            <?php $provider = vars('provider'); ?>
            <div class="text-start border rounded p-3 mb-4 mx-auto" style="max-width: 520px;">
                <div class="mb-2"><strong><?= lang('service') ?>:</strong> <?= e($service['name'] ?? '') ?></div>
                <div class="mb-2"><strong><?= lang('provider') ?>:</strong> <?= e(trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''))) ?></div>
                <div class="mb-2"><strong><?= lang('start') ?>:</strong> <?= e($appointment['start_datetime'] ?? '') ?></div>
                <div class="mb-0"><strong><?= lang('end') ?>:</strong> <?= e($appointment['end_datetime'] ?? '') ?></div>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center mt-4">
            <a href="<?= site_url() ?>" class="btn btn-primary px-4 py-2">
                <i class="fas fa-calendar-alt me-2"></i>
                <?= lang('go_to_booking_page') ?>
            </a>

            <?php if (vars('display_add_to_google_calendar') === '1'): ?>
            <a href="<?= vars(
                'add_to_google_url',
            ) ?>" id="add-to-google-calendar" class="btn btn-outline-primary px-4 py-2" target="_blank">
                <i class="fab fa-google me-2"></i>
                <?= lang('add_to_google_calendar') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<?php component('google_analytics_script', ['google_analytics_code' => vars('google_analytics_code')]); ?>
<?php component('matomo_analytics_script', [
    'matomo_analytics_url' => vars('matomo_analytics_url'),
    'matomo_analytics_site_id' => vars('matomo_analytics_site_id'),
]); ?>

<?php end_section('scripts'); ?>
