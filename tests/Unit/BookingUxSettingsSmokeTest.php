<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for booking UX, SMTP, custom CSS and statistics additions.
 */
final class BookingUxSettingsSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMigration076Exists(): void
    {
        $this->assertFileExists(
            $this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php',
        );
    }

    public function testSmtpAndStatisticsControllersExist(): void
    {
        $this->assertFileExists($this->root . '/application/controllers/Smtp_settings.php');
        $this->assertFileExists($this->root . '/application/controllers/Appointment_statistics.php');
        $this->assertFileExists($this->root . '/application/views/pages/smtp_settings.php');
        $this->assertFileExists($this->root . '/application/views/pages/appointment_statistics.php');
    }

    public function testCustomCssComponentExists(): void
    {
        $this->assertFileExists($this->root . '/application/views/components/custom_css_style.php');
        $this->assertFileExists($this->root . '/application/controllers/Custom_css.php');
        $layout = file_get_contents($this->root . '/application/views/layouts/booking_layout.php');
        $component = file_get_contents($this->root . '/application/views/components/custom_css_style.php');
        $this->assertStringContainsString("component('custom_css_style')", $layout);
        $this->assertStringContainsString("site_url('custom_css')", $component);
        $this->assertStringContainsString('hide_booking_header', $layout);
    }

    public function testMigration077HideBookingHeaderExists(): void
    {
        $this->assertFileExists(
            $this->root . '/application/migrations/077_add_hide_booking_header_setting.php',
        );
    }

    public function testSmtpTestEndpointAndUiExist(): void
    {
        $controller = file_get_contents($this->root . '/application/controllers/Smtp_settings.php');
        $view = file_get_contents($this->root . '/application/views/pages/smtp_settings.php');
        $js = file_get_contents($this->root . '/assets/js/pages/smtp_settings.js');
        $http = file_get_contents($this->root . '/assets/js/http/smtp_settings_http_client.js');

        $this->assertStringContainsString('public function test(): void', $controller);
        $this->assertStringContainsString('send_test_email', $controller);
        $this->assertStringContainsString('test-smtp-settings', $view);
        $this->assertStringContainsString('onTestClick', $js);
        $this->assertStringContainsString("siteUrl('smtp_settings/test')", $http);
    }

    public function testCalendarCancelAppointmentButtonExists(): void
    {
        $modal = file_get_contents($this->root . '/application/views/components/appointments_modal.php');
        $js = file_get_contents($this->root . '/assets/js/components/appointments_modal.js');
        $popover = file_get_contents($this->root . '/assets/js/utils/calendar_event_popover.js');

        $this->assertStringContainsString('id="cancel-appointment"', $modal);
        $this->assertStringContainsString("lang('cancel_appointment')", $modal);
        $this->assertStringContainsString('$cancelAppointment.on(', $js);
        $this->assertStringContainsString("'cancel_appointment'", $popover);
    }

    public function testStatisticsStatusUsesConfiguredOptions(): void
    {
        $view = file_get_contents($this->root . '/application/views/pages/appointment_statistics.php');
        $controller = file_get_contents($this->root . '/application/controllers/Appointment_statistics.php');

        $this->assertStringContainsString('appointment_status_options', $view);
        $this->assertStringContainsString('<select class="form-select" id="filter-status"', $view);
        $this->assertStringContainsString("setting('appointment_status_options'", $controller);
    }

    public function testManageModeDateTimeOnlySettingAndCancelLabel(): void
    {
        $this->assertFileExists(
            $this->root . '/application/migrations/078_add_booking_manage_date_time_only_setting.php',
        );

        $settings = file_get_contents($this->root . '/application/views/pages/booking_settings.php');
        $cancelFrame = file_get_contents($this->root . '/application/views/components/booking_cancellation_frame.php');
        $bookingJs = file_get_contents($this->root . '/assets/js/pages/booking.js');
        $bookingController = file_get_contents($this->root . '/application/controllers/Booking.php');
        $api = file_get_contents($this->root . '/application/controllers/api/v1/Appointments_api_v1.php');

        $this->assertStringContainsString('booking_manage_date_time_only', $settings);
        $this->assertStringContainsString("lang('cancel_appointment')", $cancelFrame);
        $this->assertStringContainsString('applyManageModeDateTimeOnly', $bookingJs);
        $this->assertStringContainsString('booking_manage_date_time_only', $bookingController);
        $this->assertStringContainsString("request('status'", $api);
        $this->assertStringContainsString("request('createdById')", $api);
    }

    public function testBookingJsTracksFieldLevelEventsAndSkipConfirmation(): void
    {
        $js = file_get_contents($this->root . '/assets/js/pages/booking.js');

        $this->assertNotFalse($js);
        $this->assertStringContainsString('trackBookingProgressDebounced', $js);
        $this->assertStringContainsString("trackBookingProgress('date_selected'", $js);
        $this->assertStringContainsString("trackBookingProgress('time_selected')", $js);
        $this->assertStringContainsString('booking_skip_confirmation_step', $js);
        $this->assertStringContainsString('submitBookingWithoutConfirmation', $js);
    }

    public function testGermanTranslationsCoverNewKeys(): void
    {
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        $this->assertNotFalse($de);
        $this->assertStringContainsString("\$lang['smtp_settings']", $de);
        $this->assertStringContainsString("\$lang['appointment_statistics']", $de);
        $this->assertStringContainsString("\$lang['custom_css_enabled']", $de);
        $this->assertStringContainsString("\$lang['booking_skip_confirmation_step']", $de);
        $this->assertStringContainsString("\$lang['booking_end_screen_title']", $de);
        $this->assertStringContainsString("\$lang['hide_booking_header']", $de);
        $this->assertStringContainsString("\$lang['smtp_send_test_email']", $de);
        $this->assertStringContainsString("\$lang['cancel_appointment']", $de);
        $this->assertStringContainsString("\$lang['booking_manage_date_time_only']", $de);
    }

    public function testEmailMessagesPrefersBackendSmtpSettings(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Email_messages.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("setting('smtp_enabled'", $source);
        $this->assertStringContainsString("setting('smtp_host'", $source);
    }

    public function testDefaultCustomCssDoesNotHideCustomFields(): void
    {
        $source = file_get_contents(
            $this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php',
        );

        $this->assertNotFalse($source);
        $this->assertStringContainsString('--gold-primary', $source);
        $this->assertStringNotContainsString('custom-field-1', $source);
        $this->assertStringContainsString('Custom fields stay visible', $source);
    }
}
