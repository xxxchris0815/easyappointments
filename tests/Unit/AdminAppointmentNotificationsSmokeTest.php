<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for admin appointment notification controls and audit logging.
 */
class AdminAppointmentNotificationsSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSettingMigrationAndUiExist(): void
    {
        $migration = file_get_contents(
            $this->root . '/application/migrations/092_add_admin_appointment_notifications_setting.php',
        );
        $view = file_get_contents($this->root . '/application/views/pages/booking_settings.php');
        $notifications = file_get_contents($this->root . '/application/libraries/Notifications.php');
        $en = file_get_contents($this->root . '/application/language/english/translations_lang.php');
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        $this->assertStringContainsString('admin_appointment_notifications', $migration);
        $this->assertStringContainsString('data-field="admin_appointment_notifications"', $view);
        $this->assertStringContainsString('admin_appointment_notifications', $notifications);
        $this->assertStringContainsString('appointment_notifications.log', $notifications);
        $this->assertStringContainsString('skip_provider_not_assigned', $notifications);
        $this->assertStringContainsString('appointment_booked_admin_notification', $en);
        $this->assertStringContainsString('appointment_booked_admin_notification', $de);
        $this->assertStringContainsString('admin_appointment_notifications_hint', $de);
    }
}
