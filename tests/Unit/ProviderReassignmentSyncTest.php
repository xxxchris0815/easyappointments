<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks: provider reassignment must move Google/CalDAV events.
 */
class ProviderReassignmentSyncTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSynchronizationComparesNewProviderId(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Synchronization.php');

        $this->assertStringContainsString('function move_appointment_on_provider_change', $source);
        $this->assertStringContainsString('function provider_changed', $source);
        $this->assertStringContainsString('$new_provider_id = null', $source);
        $this->assertStringNotContainsString(
            '(int) $existing_provider_id !== (int) $existing_appointment[\'id_users_provider\']',
            $source,
        );
        $this->assertStringContainsString('recreating:', $source);
    }

    public function testBookingAndCalendarCallProviderMove(): void
    {
        $booking = file_get_contents($this->root . '/application/controllers/Booking.php');
        $calendar = file_get_contents($this->root . '/application/controllers/Calendar.php');
        $api = file_get_contents($this->root . '/application/controllers/api/v1/Appointments_api_v1.php');

        $this->assertStringContainsString('move_appointment_on_provider_change', $booking);
        $this->assertStringContainsString('provider_changed', $booking);
        $this->assertStringContainsString('appointment_reassigned_to_another_provider', $booking);

        $this->assertStringContainsString(
            'remove_appointment_on_provider_change(',
            $calendar,
        );
        $this->assertStringContainsString('(int) $appointment[\'id_users_provider\']', $calendar);
        $this->assertStringContainsString('appointment_reassigned_to_another_provider', $calendar);

        $this->assertStringContainsString('move_appointment_on_provider_change', $api);
        $this->assertStringContainsString('provider_reassigned', $api);
    }

    public function testAnyProviderAssignmentExcludesCurrentAppointment(): void
    {
        $lib = file_get_contents($this->root . '/application/libraries/Any_provider_assignment.php');
        $booking = file_get_contents($this->root . '/application/controllers/Booking.php');

        $this->assertStringContainsString('?int $exclude_appointment_id = null', $lib);
        $this->assertStringContainsString('$exclude_appointment_id', $lib);
        $this->assertStringContainsString('exclude_appointment_id', $booking);
    }

    public function testAnyProviderReschedulePrefersExistingProvider(): void
    {
        $lib = file_get_contents($this->root . '/application/libraries/Any_provider_assignment.php');
        $booking = file_get_contents($this->root . '/application/controllers/Booking.php');

        $this->assertStringContainsString('?int $preferred_provider_id = null', $lib);
        $this->assertStringContainsString('keep the current closer when still free', $booking);
        $this->assertStringContainsString('$preferred_provider_id', $booking);
        $this->assertStringContainsString(
            'if ((int) $candidate[\'id\'] === (int) $preferred_provider_id)',
            $lib,
        );
    }

    public function testDeletedNotificationCanSkipCustomerAndAdmins(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Notifications.php');

        $this->assertStringContainsString('bool $notify_customer = true', $source);
        $this->assertStringContainsString('bool $notify_admins = true', $source);
    }

    public function testTranslationsIncludeReassignmentReason(): void
    {
        $en = file_get_contents($this->root . '/application/language/english/translations_lang.php');
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        $this->assertStringContainsString("\$lang['appointment_reassigned_to_another_provider']", $en);
        $this->assertStringContainsString("\$lang['appointment_reassigned_to_another_provider']", $de);
    }
}
