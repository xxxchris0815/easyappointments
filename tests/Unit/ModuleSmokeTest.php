<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Lightweight smoke checks for custom fork helpers/config assumptions.
 */
final class ModuleSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMaxCustomFieldsConstantExists(): void
    {
        if (!defined('MAX_CUSTOM_FIELDS')) {
            require_once $this->root . '/application/config/constants.php';
        }

        $this->assertSame(20, MAX_CUSTOM_FIELDS);
    }

    public function testAppointmentWebhookActionConstantsExist(): void
    {
        $source = file_get_contents($this->root . '/application/config/constants.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("const WEBHOOK_APPOINTMENT_SAVE = 'appointment_save';", $source);
        $this->assertStringContainsString("const WEBHOOK_APPOINTMENT_CREATE = 'appointment_create';", $source);
        $this->assertStringContainsString("const WEBHOOK_APPOINTMENT_UPDATE = 'appointment_update';", $source);
        $this->assertStringContainsString("const WEBHOOK_APPOINTMENT_DELETE = 'appointment_delete';", $source);
        $this->assertStringContainsString("const WEBHOOK_APPOINTMENT_REMINDER = 'appointment_reminder';", $source);
    }

    public function testZoomClientLibraryExists(): void
    {
        $this->assertFileExists($this->root . '/application/libraries/Zoom_client.php');
        $this->assertFileExists($this->root . '/application/controllers/Zoom_settings.php');
    }

    public function testBookingTrackerLibraryExists(): void
    {
        $this->assertFileExists($this->root . '/application/libraries/Booking_tracker.php');
    }

    public function testMauticClientLibraryExists(): void
    {
        $this->assertFileExists($this->root . '/application/libraries/Mautic_client.php');
        $this->assertFileExists($this->root . '/application/controllers/Mautic_settings.php');
    }

    public function testReminderModuleFilesExist(): void
    {
        $this->assertFileExists($this->root . '/application/libraries/Reminders.php');
        $this->assertFileExists($this->root . '/application/models/Appointment_reminder_deliveries_model.php');
        $this->assertFileExists($this->root . '/application/views/emails/appointment_reminder_email.php');
        $this->assertFileExists($this->root . '/application/migrations/075_add_appointment_reminders.php');
        $this->assertFileExists($this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php');
    }

    public function testRemindersLibraryExposesExpectedApi(): void
    {
        require_once $this->root . '/application/libraries/Reminders.php';

        $this->assertTrue(method_exists(Reminders::class, 'is_enabled'));
        $this->assertTrue(method_exists(Reminders::class, 'get_rules'));
        $this->assertTrue(method_exists(Reminders::class, 'schedule_for_appointment'));
        $this->assertTrue(method_exists(Reminders::class, 'clear_for_appointment'));
        $this->assertTrue(method_exists(Reminders::class, 'run'));
    }

    public function testConsoleExposesRemindersCommand(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Console.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('function reminders()', $source);
        $this->assertStringContainsString('console reminders', $source);
    }

    public function testBookingSettingsIncludesReminderControls(): void
    {
        $view = file_get_contents($this->root . '/application/views/pages/booking_settings.php');
        $js = file_get_contents($this->root . '/assets/js/pages/booking_settings.js');

        $this->assertNotFalse($view);
        $this->assertNotFalse($js);
        $this->assertStringContainsString('appointment_reminders_enabled', $view);
        $this->assertStringContainsString('appointment-reminder-rules', $view);
        $this->assertStringContainsString('renderReminderRules', $js);
        $this->assertStringContainsString('syncReminderField', $js);
    }

    public function testWebhooksClientExposesAppointmentHelpers(): void
    {
        require_once $this->root . '/application/libraries/Webhooks_client.php';

        $this->assertTrue(method_exists(Webhooks_client::class, 'trigger_appointment_saved'));
        $this->assertTrue(method_exists(Webhooks_client::class, 'trigger_appointment_deleted'));
        $this->assertTrue(method_exists(Webhooks_client::class, 'trigger_appointment_reminder'));
        $this->assertTrue(method_exists(Webhooks_client::class, 'prepare_appointment_payload'));
    }

    public function testCustomForkMigrationsExist(): void
    {
        $this->assertFileExists($this->root . '/application/migrations/070_add_custom_fork_features.php');
        $this->assertFileExists($this->root . '/application/migrations/071_add_mautic_api_settings.php');
        $this->assertFileExists($this->root . '/application/migrations/072_add_appointment_secretary_id.php');
        $this->assertFileExists($this->root . '/application/migrations/073_remove_appointment_secretary_id.php');
        $this->assertFileExists($this->root . '/application/migrations/074_add_soft_cancel_appointments.php');
        $this->assertFileExists($this->root . '/application/migrations/075_add_appointment_reminders.php');
        $this->assertFileExists($this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php');
    }

    public function testAppointmentsModelMapsCreatedByIdOnly(): void
    {
        $source = file_get_contents($this->root . '/application/models/Appointments_model.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("'createdById' => 'id_users_created_by'", $source);
        $this->assertStringNotContainsString("'secretaryId' => 'id_users_secretary'", $source);
        $this->assertStringContainsString('function cancel(', $source);
        $this->assertStringContainsString('function is_cancelled(', $source);
    }
}
