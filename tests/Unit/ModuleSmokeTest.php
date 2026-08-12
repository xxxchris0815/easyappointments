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

    public function testWebhooksClientExposesAppointmentHelpers(): void
    {
        require_once $this->root . '/application/libraries/Webhooks_client.php';

        $this->assertTrue(method_exists(Webhooks_client::class, 'trigger_appointment_saved'));
        $this->assertTrue(method_exists(Webhooks_client::class, 'trigger_appointment_deleted'));
        $this->assertTrue(method_exists(Webhooks_client::class, 'prepare_appointment_payload'));
    }

    public function testCustomForkMigrationsExist(): void
    {
        $this->assertFileExists($this->root . '/application/migrations/070_add_custom_fork_features.php');
        $this->assertFileExists($this->root . '/application/migrations/071_add_mautic_api_settings.php');
        $this->assertFileExists($this->root . '/application/migrations/072_add_appointment_secretary_id.php');
        $this->assertFileExists($this->root . '/application/migrations/073_remove_appointment_secretary_id.php');
        $this->assertFileExists($this->root . '/application/migrations/074_add_soft_cancel_appointments.php');
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
