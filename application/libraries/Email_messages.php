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

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Email messages library.
 *
 * Handles the email messaging related functionality.
 *
 * @package Libraries
 */
class Email_messages
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Email_messages constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('email');
        $this->CI->load->library('ics_file');
        $this->CI->load->library('timezones');
    }

    /**
     * Send an email with the appointment details.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @param array $settings App settings.
     * @param string $subject Email subject.
     * @param string $message Email message.
     * @param string $appointment_link Appointment unique URL.
     * @param string $recipient_email Recipient email address.
     * @param string $ics_stream ICS file contents.
     * @param string|null $timezone Custom timezone.
     *
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function send_appointment_saved(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
        string $subject,
        string $message,
        string $appointment_link,
        string $recipient_email,
        string $ics_stream,
        ?string $timezone = null,
    ): void {
        [$appointment, $timezone] = $this->localize_appointment_for_email($appointment, $provider, $timezone);

        $html = $this->CI->load->view(
            'emails/appointment_saved_email',
            [
                'subject' => $subject,
                'message' => $message,
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'timezone' => $timezone,
                'appointment_link' => $appointment_link,
            ],
            true,
        );

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);

        $php_mailer->addStringAttachment($ics_stream, 'invitation.ics', PHPMailer::ENCODING_BASE64, 'text/calendar');

        $php_mailer->send();
    }

    /**
     * Send an appointment reminder email.
     */
    public function send_appointment_reminder(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
        string $subject,
        string $message,
        string $appointment_link,
        string $recipient_email,
        ?string $timezone = null,
        array $reminder = [],
    ): void {
        [$appointment, $timezone] = $this->localize_appointment_for_email($appointment, $provider, $timezone);

        $html = $this->CI->load->view(
            'emails/appointment_reminder_email',
            [
                'subject' => $subject,
                'message' => $message,
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'timezone' => $timezone,
                'appointment_link' => $appointment_link,
                'reminder' => $reminder,
            ],
            true,
        );

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
        $php_mailer->send();
    }

    /**
     * Send an email with the appointment removal details.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @param array $settings App settings.
     * @param string $recipient_email Recipient email address.
     * @param string|null $reason Removal reason.
     * @param string|null $timezone Custom timezone.
     *
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function send_appointment_deleted(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
        string $recipient_email,
        ?string $reason = null,
        ?string $timezone = null,
    ): void {
        [$appointment, $timezone] = $this->localize_appointment_for_email($appointment, $provider, $timezone);

        $html = $this->CI->load->view(
            'emails/appointment_deleted_email',
            [
                'appointment' => $appointment,
                'service' => $service,
                'provider' => $provider,
                'customer' => $customer,
                'settings' => $settings,
                'timezone' => $timezone,
                'reason' => $reason,
            ],
            true,
        );

        $subject = lang('appointment_cancelled_title');

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Send the account recovery details.
     *
     * @param string $password New password.
     * @param string $recipient_email Recipient email address.
     * @param array $settings App settings.
     *
     * @throws Exception
     */
    public function send_password(string $password, string $recipient_email, array $settings): void
    {
        $html = $this->CI->load->view(
            'emails/account_recovery_email',
            [
                'subject' => lang('new_account_password'),
                'message' => str_replace('$password', '<strong>' . $password . '</strong>', lang('new_password_is')),
                'settings' => $settings,
            ],
            true,
        );

        $subject = lang('new_account_password');

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Send the password reset link.
     *
     * @param string $reset_link The password reset URL.
     * @param string $recipient_email Recipient email address.
     * @param array $settings App settings.
     *
     * @throws Exception
     */
    public function send_password_reset_link(string $reset_link, string $recipient_email, array $settings): void
    {
        $html = $this->CI->load->view(
            'emails/password_reset_email',
            [
                'subject' => lang('password_reset_request'),
                'message' => lang('password_reset_email_message'),
                'reset_link' => $reset_link,
                'settings' => $settings,
            ],
            true,
        );

        $subject = lang('password_reset_request');

        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);

        $php_mailer->send();
    }

    /**
     * Send a simple HTML test email using the active mail configuration.
     *
     * @throws Exception
     */
    public function send_test_email(string $recipient_email, string $subject, string $html): void
    {
        $php_mailer = $this->get_php_mailer($recipient_email, $subject, $html);
        $php_mailer->send();
    }

    /**
     * Localize appointment datetimes for email display.
     *
     * Appointment values are stored as naive local times in the provider timezone.
     * When the booking timezone selector is hidden, keep those wall-clock values and
     * label them with the system default timezone (business local time, e.g. Berlin)
     * instead of converting to a recipient timezone that is often still UTC.
     *
     * @return array{0: array, 1: string} Updated appointment and display timezone name
     */
    private function localize_appointment_for_email(
        array $appointment,
        array $provider,
        ?string $recipient_timezone,
    ): array {
        $system_timezone = setting('default_timezone') ?: 'UTC';
        $provider_timezone_name = !empty($provider['timezone']) ? (string) $provider['timezone'] : $system_timezone;

        $hide_selector = filter_var(setting('hide_booking_timezone_selector'), FILTER_VALIDATE_BOOLEAN);

        if ($hide_selector) {
            // Booking UI already showed these times as business-local; do not shift them.
            return [$appointment, $system_timezone];
        }

        $display_timezone_name = $recipient_timezone ?: $provider_timezone_name;

        try {
            $provider_tz = new DateTimeZone($provider_timezone_name);
            $display_tz = new DateTimeZone($display_timezone_name);
        } catch (Throwable) {
            return [$appointment, $system_timezone];
        }

        if ($display_timezone_name === $provider_timezone_name) {
            return [$appointment, $display_timezone_name];
        }

        $appointment_start = new DateTime((string) $appointment['start_datetime'], $provider_tz);
        $appointment_end = new DateTime((string) $appointment['end_datetime'], $provider_tz);
        $appointment_start->setTimezone($display_tz);
        $appointment_end->setTimezone($display_tz);
        $appointment['start_datetime'] = $appointment_start->format('Y-m-d H:i:s');
        $appointment['end_datetime'] = $appointment_end->format('Y-m-d H:i:s');

        return [$appointment, $display_timezone_name];
    }

    /**
     * Create PHP Mailer instance based on the email configuration.
     *
     * @param string|null $recipient_email
     * @param string|null $subject
     * @param string|null $html
     *
     * @return PHPMailer
     *
     * @throws Exception
     */
    private function get_php_mailer(
        ?string $recipient_email = null,
        ?string $subject = null,
        ?string $html = null,
    ): PHPMailer {
        $php_mailer = new PHPMailer(true);

        $php_mailer->CharSet = 'UTF-8';
        $php_mailer->SMTPDebug = config('smtp_debug') ? SMTP::DEBUG_SERVER : null;

        // Prefer backend SMTP settings when enabled; fall back to config/email.php.
        $use_db_smtp = filter_var(setting('smtp_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $use_smtp = $use_db_smtp || config('protocol') === 'smtp';

        if ($use_smtp) {
            $php_mailer->isSMTP();
            $php_mailer->Host = $use_db_smtp ? setting('smtp_host', '') : config('smtp_host');
            $php_mailer->SMTPAuth = true;
            $php_mailer->Username = $use_db_smtp ? setting('smtp_user', '') : config('smtp_user');
            $php_mailer->Password = $use_db_smtp ? setting('smtp_pass', '') : config('smtp_pass');
            $php_mailer->SMTPSecure = $use_db_smtp ? setting('smtp_crypto', '') : config('smtp_crypto');
            $php_mailer->Port = (int) ($use_db_smtp ? setting('smtp_port', 587) : config('smtp_port'));
        }

        $from_name =
            ($use_db_smtp ? setting('smtp_from_name', '') : '') ?:
            config('from_name') ?:
            setting('company_name');
        $from_address =
            ($use_db_smtp ? setting('smtp_from_address', '') : '') ?:
            config('from_address') ?:
            setting('company_email');
        $reply_to_address =
            ($use_db_smtp ? setting('smtp_reply_to', '') : '') ?:
            config('reply_to') ?:
            setting('company_email');

        $php_mailer->setFrom($from_address, $from_name);
        $php_mailer->addReplyTo($reply_to_address);

        if ($recipient_email) {
            $php_mailer->addAddress($recipient_email);
        }

        if ($subject) {
            $php_mailer->Subject = $subject;
        }

        if ($html) {
            $plain_text = str_replace(["\n\n", "\n\n\n"], '', strip_tags($html));

            if (config('mailtype') === 'html') {
                $php_mailer->isHTML();
            } else {
                $html = $plain_text;
            }

            $php_mailer->Body = $html;
            $php_mailer->AltBody = $plain_text;
        }

        $php_mailer->addEmbeddedImage(FCPATH . 'assets/img/logo.png', 'logo.png', 'logo.png', 'base64', 'image/png');

        return $php_mailer;
    }
}
