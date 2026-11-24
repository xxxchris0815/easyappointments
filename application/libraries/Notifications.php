<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Notifications library.
 *
 * Handles the notifications related functionality.
 *
 * @package Libraries
 */
class Notifications
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Notifications constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email_messages');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
    }

    /**
     * Send the required notifications, related to an appointment creation/modification.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     * @param bool|false $manage_mode Manage mode.
     */
    public function notify_appointment_saved(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        bool $manage_mode = false,
    ): void {
        try {
            $current_language = config('language');
            $appointment_id = isset($appointment['id']) ? (int) $appointment['id'] : null;
            $assigned_provider_id = (int) ($appointment['id_users_provider'] ?? 0);

            // Always notify against the provider stored on the appointment (guards stale arrays).
            if ($assigned_provider_id > 0 && (int) ($provider['id'] ?? 0) !== $assigned_provider_id) {
                $this->audit_notification([
                    'event' => 'appointment_saved',
                    'appointment_id' => $appointment_id,
                    'action' => 'reload_provider',
                    'passed_provider_id' => (int) ($provider['id'] ?? 0),
                    'assigned_provider_id' => $assigned_provider_id,
                ]);
                $provider = $this->CI->providers_model->find($assigned_provider_id);
            }

            $customer_link = site_url('booking/reschedule/' . $appointment['hash']);
            $provider_link = site_url('calendar/reschedule/' . $appointment['hash']);
            $ics_stream = $this->CI->ics_file->get_stream($appointment, $service, $provider, $customer);
            $provider_label = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));

            // email => recipient meta (first role wins for dedupe)
            $recipients = [];

            $queue = function (
                string $role,
                string $email,
                array $user,
                string $subject,
                string $message,
                string $link,
                ?string $timezone,
            ) use (&$recipients, $appointment_id, $assigned_provider_id, $provider_label): void {
                $email = strtolower(trim($email));

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return;
                }

                if (isset($recipients[$email])) {
                    $this->audit_notification([
                        'event' => 'appointment_saved',
                        'appointment_id' => $appointment_id,
                        'action' => 'dedupe_skip',
                        'role' => $role,
                        'email' => $email,
                        'kept_role' => $recipients[$email]['role'],
                        'assigned_provider_id' => $assigned_provider_id,
                        'assigned_provider_name' => $provider_label,
                    ]);

                    return;
                }

                $recipients[$email] = [
                    'role' => $role,
                    'email' => $email,
                    'user' => $user,
                    'subject' => $subject,
                    'message' => $message,
                    'link' => $link,
                    'timezone' => $timezone,
                ];
            };

            // Customer
            if (
                !empty($customer['email']) &&
                filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN)
            ) {
                config(['language' => $customer['language'] ?? $current_language]);
                $this->CI->lang->load('translations');
                $queue(
                    'customer',
                    (string) $customer['email'],
                    $customer,
                    $manage_mode ? lang('appointment_details_changed') : lang('appointment_booked'),
                    $manage_mode ? '' : lang('thank_you_for_appointment'),
                    $customer_link,
                    $customer['timezone'] ?? null,
                );
            }

            // Assigned provider only (never all providers).
            $send_provider = filter_var(
                $this->CI->providers_model->get_setting((int) $provider['id'], 'notifications'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($send_provider && $assigned_provider_id > 0 && (int) $provider['id'] === $assigned_provider_id) {
                config(['language' => $provider['language'] ?? $current_language]);
                $this->CI->lang->load('translations');
                $queue(
                    'provider',
                    (string) $provider['email'],
                    $provider,
                    $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan'),
                    $manage_mode ? '' : lang('appointment_link_description'),
                    $provider_link,
                    $provider['timezone'] ?? null,
                );
            } elseif ($send_provider) {
                $this->audit_notification([
                    'event' => 'appointment_saved',
                    'appointment_id' => $appointment_id,
                    'action' => 'skip_provider_not_assigned',
                    'provider_id' => (int) ($provider['id'] ?? 0),
                    'assigned_provider_id' => $assigned_provider_id,
                ]);
            }

            // Admins (company-wide) — optional via booking setting + per-admin toggle.
            $admin_notifications_enabled = filter_var(
                setting('admin_appointment_notifications', '1'),
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($admin_notifications_enabled) {
                foreach ($this->CI->admins_model->get() as $admin) {
                    if (!$this->user_wants_notifications($admin)) {
                        continue;
                    }

                    config(['language' => $admin['language'] ?? $current_language]);
                    $this->CI->lang->load('translations');
                    $admin_message = str_replace(
                        '{provider}',
                        $provider_label !== '' ? $provider_label : ('#' . $assigned_provider_id),
                        lang('appointment_admin_notification_message'),
                    );
                    $queue(
                        'admin',
                        (string) $admin['email'],
                        $admin,
                        $manage_mode
                            ? lang('appointment_changed_admin_notification')
                            : lang('appointment_booked_admin_notification'),
                        $admin_message,
                        $provider_link,
                        $admin['timezone'] ?? null,
                    );
                }
            } else {
                $this->audit_notification([
                    'event' => 'appointment_saved',
                    'appointment_id' => $appointment_id,
                    'action' => 'skip_admins_disabled',
                    'assigned_provider_id' => $assigned_provider_id,
                ]);
            }

            // Secretaries: only for appointments they themselves created (id_users_created_by).
            foreach ($this->CI->secretaries_model->get() as $secretary) {
                if (!$this->user_wants_notifications($secretary)) {
                    continue;
                }

                if (!$this->secretary_owns_appointment($secretary, $appointment)) {
                    $this->audit_notification([
                        'event' => 'appointment_saved',
                        'appointment_id' => $appointment_id,
                        'action' => 'skip_secretary_not_creator',
                        'secretary_id' => (int) ($secretary['id'] ?? 0),
                        'created_by' => (int) ($appointment['id_users_created_by'] ?? 0),
                        'assigned_provider_id' => $assigned_provider_id,
                    ]);
                    continue;
                }

                config(['language' => $secretary['language'] ?? $current_language]);
                $this->CI->lang->load('translations');
                $queue(
                    'secretary',
                    (string) $secretary['email'],
                    $secretary,
                    $manage_mode ? lang('appointment_details_changed') : lang('appointment_added_to_your_plan'),
                    $manage_mode ? '' : lang('appointment_link_description'),
                    $provider_link,
                    $secretary['timezone'] ?? null,
                );
            }

            foreach ($recipients as $recipient) {
                config(['language' => $recipient['user']['language'] ?? $current_language]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_saved(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $recipient['subject'],
                        $recipient['message'],
                        $recipient['link'],
                        $recipient['email'],
                        $ics_stream,
                        $recipient['timezone'],
                    );

                    $this->audit_notification([
                        'event' => 'appointment_saved',
                        'appointment_id' => $appointment_id,
                        'action' => 'sent',
                        'role' => $recipient['role'],
                        'email' => $recipient['email'],
                        'assigned_provider_id' => $assigned_provider_id,
                        'assigned_provider_name' => $provider_label,
                        'manage_mode' => $manage_mode,
                    ]);
                } catch (Throwable $e) {
                    $this->log_exception(
                        $e,
                        'appointment-saved to ' . $recipient['role'],
                        $appointment_id,
                    );
                }
            }
        } catch (Throwable $e) {
            $this->log_exception($e, 'appointment-saved (general exception)', $appointment['id'] ?? null);
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Send the required notifications, related to an appointment removal.
     *
     * @param array $appointment Appointment data.
     * @param array $service Service data.
     * @param array $provider Provider data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     */
    public function notify_appointment_deleted(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
        string $cancellation_reason = '',
        bool $notify_customer = true,
        bool $notify_admins = true,
    ): void {
        try {
            $current_language = config('language');

            $appointment_id = isset($appointment['id']) ? (int) $appointment['id'] : null;
            $assigned_provider_id = (int) ($appointment['id_users_provider'] ?? $provider['id'] ?? 0);

            // Notify provider (only the appointment's provider).
            $send_provider =
                $assigned_provider_id > 0 &&
                (int) ($provider['id'] ?? 0) === $assigned_provider_id &&
                filter_var(
                    $this->CI->providers_model->get_setting($provider['id'], 'notifications'),
                    FILTER_VALIDATE_BOOLEAN,
                );

            if ($send_provider === true) {
                config(['language' => $provider['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $provider['email'],
                        $cancellation_reason,
                        $provider['timezone'],
                    );
                    $this->audit_notification([
                        'event' => 'appointment_deleted',
                        'appointment_id' => $appointment_id,
                        'action' => 'sent',
                        'role' => 'provider',
                        'email' => strtolower(trim((string) $provider['email'])),
                        'assigned_provider_id' => $assigned_provider_id,
                    ]);
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to provider', $appointment['id'] ?? null);
                }
            }

            // Notify customer.
            $send_customer =
                $notify_customer &&
                !empty($customer['email']) &&
                filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

            if ($send_customer === true) {
                config(['language' => $customer['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $customer['email'],
                        $cancellation_reason,
                        $customer['timezone'],
                    );
                    $this->audit_notification([
                        'event' => 'appointment_deleted',
                        'appointment_id' => $appointment_id,
                        'action' => 'sent',
                        'role' => 'customer',
                        'email' => strtolower(trim((string) $customer['email'])),
                        'assigned_provider_id' => $assigned_provider_id,
                    ]);
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to customer', $appointment['id'] ?? null);
                }
            }

            // Notify admins.
            $admin_notifications_enabled = filter_var(
                setting('admin_appointment_notifications', '1'),
                FILTER_VALIDATE_BOOLEAN,
            );
            $admins =
                $notify_admins && $admin_notifications_enabled ? $this->CI->admins_model->get() : [];

            foreach ($admins as $admin) {
                if (!$this->user_wants_notifications($admin)) {
                    continue;
                }

                config(['language' => $admin['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $admin['email'],
                        $cancellation_reason,
                        $admin['timezone'],
                    );
                    $this->audit_notification([
                        'event' => 'appointment_deleted',
                        'appointment_id' => $appointment_id,
                        'action' => 'sent',
                        'role' => 'admin',
                        'email' => strtolower(trim((string) $admin['email'])),
                        'assigned_provider_id' => $assigned_provider_id,
                    ]);
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to admin', $appointment['id'] ?? null);
                }
            }

            // Notify secretaries (only appointments they created).
            $secretaries = $this->CI->secretaries_model->get();

            foreach ($secretaries as $secretary) {
                if (!$this->user_wants_notifications($secretary)) {
                    continue;
                }

                if (!$this->secretary_owns_appointment($secretary, $appointment)) {
                    $this->audit_notification([
                        'event' => 'appointment_deleted',
                        'appointment_id' => $appointment_id,
                        'action' => 'skip_secretary_not_creator',
                        'secretary_id' => (int) ($secretary['id'] ?? 0),
                        'created_by' => (int) ($appointment['id_users_created_by'] ?? 0),
                        'assigned_provider_id' => $assigned_provider_id,
                    ]);
                    continue;
                }

                config(['language' => $secretary['language']]);
                $this->CI->lang->load('translations');

                try {
                    $this->CI->email_messages->send_appointment_deleted(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                        $secretary['email'],
                        $cancellation_reason,
                        $secretary['timezone'],
                    );
                    $this->audit_notification([
                        'event' => 'appointment_deleted',
                        'appointment_id' => $appointment_id,
                        'action' => 'sent',
                        'role' => 'secretary',
                        'email' => strtolower(trim((string) $secretary['email'])),
                        'assigned_provider_id' => $assigned_provider_id,
                        'created_by' => (int) ($appointment['id_users_created_by'] ?? 0),
                    ]);
                } catch (Throwable $e) {
                    $this->log_exception($e, 'appointment-deleted to secretary', $appointment['id'] ?? null);
                }
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Notifications - Could not email cancellation details of appointment (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        } finally {
            config(['language' => $current_language ?? 'english']);
            $this->CI->lang->load('translations');
        }
    }

    /**
     * Send an appointment reminder notification to the customer.
     */
    public function notify_appointment_reminder(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $reminder = [],
    ): void {
        $send_customer =
            !empty($customer['email']) && filter_var(setting('customer_notifications'), FILTER_VALIDATE_BOOLEAN);

        if (!$send_customer) {
            throw new RuntimeException('Customer reminder email is disabled or customer has no email.');
        }

        $current_language = config('language');

        try {
            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            config(['language' => $customer['language'] ?? $current_language]);
            $this->CI->lang->load('translations');

            $appointment_link = site_url('booking/reschedule/' . $appointment['hash']);
            $subject = lang('appointment_reminder_subject');
            $message = lang('appointment_reminder_message');

            $this->CI->email_messages->send_appointment_reminder(
                $appointment,
                $provider,
                $service,
                $customer,
                $settings,
                $subject,
                $message,
                $appointment_link,
                $customer['email'],
                $customer['timezone'] ?? null,
                $reminder,
            );
        } finally {
            config(['language' => $current_language]);
            $this->CI->lang->load('translations');
        }
    }

    private function log_exception(Throwable $e, string $message, ?int $appointment_id): void
    {
        log_message(
            'error',
            'Notifications - Could not email ' . $message . ' (' . ($appointment_id ?? '-') . ') : ' . $e->getMessage(),
        );
        log_message('error', $e->getTraceAsString());
    }

    /**
     * Whether a staff user has notifications enabled.
     */
    private function user_wants_notifications(array $user): bool
    {
        return filter_var($user['settings']['notifications'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Secretaries only receive mails for appointments they personally created.
     */
    private function secretary_owns_appointment(array $secretary, array $appointment): bool
    {
        $secretary_id = (int) ($secretary['id'] ?? 0);
        $created_by = (int) ($appointment['id_users_created_by'] ?? 0);

        return $secretary_id > 0 && $created_by > 0 && $secretary_id === $created_by;
    }

    /**
     * Append a structured notification audit line for debugging recipients.
     */
    private function audit_notification(array $payload): void
    {
        $payload['ts'] = date('c');

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        $dir = FCPATH . 'storage/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents(
            rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'appointment_notifications.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
