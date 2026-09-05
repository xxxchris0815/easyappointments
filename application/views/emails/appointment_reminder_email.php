<?php
/**
 * @var string $subject
 * @var string $message
 * @var array $appointment
 * @var array $service
 * @var array $provider
 * @var array $customer
 * @var array $settings
 * @var string|null $timezone
 * @var string $appointment_link
 * @var array $reminder
 */
$company_name = $settings['company_name'] ?? 'Easy!Appointments';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($subject) ?></title>
</head>
<body style="font-family:sans-serif;background:#f6f6f6;padding:24px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">
    <tr>
        <td style="padding:24px;">
            <h2 style="margin:0 0 12px;"><?= e($subject) ?></h2>
            <p><?= e($message) ?></p>
            <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
            <p><strong><?= lang('service') ?>:</strong> <?= e($service['name'] ?? '') ?></p>
            <p><strong><?= lang('provider') ?>:</strong> <?= e(trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''))) ?></p>
            <p><strong><?= lang('start') ?>:</strong> <?= e($appointment['start_datetime'] ?? '') ?></p>
            <p><strong><?= lang('end') ?>:</strong> <?= e($appointment['end_datetime'] ?? '') ?></p>
            <?php if (!empty($appointment['meeting_link'])): ?>
                <p><strong><?= lang('meeting_link') ?>:</strong>
                    <a href="<?= e($appointment['meeting_link']) ?>"><?= e($appointment['meeting_link']) ?></a>
                </p>
            <?php endif; ?>
            <?php if (!empty($appointment_link)): ?>
                <p><a href="<?= e($appointment_link) ?>"><?= lang('appointment_link_title') ?></a></p>
            <?php endif; ?>
            <p style="color:#888;font-size:12px;margin-top:24px;"><?= e($company_name) ?></p>
        </td>
    </tr>
</table>
</body>
</html>
