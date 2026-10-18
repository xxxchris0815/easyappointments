<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Soft-cancel must always collect a status/reason in the calendar UI and API.
 */
class AppointmentCancelRequiredReasonTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCalendarDeleteAppointmentRequiresCancellationReason(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Calendar.php');

        $this->assertStringContainsString("check('cancellation_reason', 'string')", $source);
        $this->assertStringContainsString('A cancellation status/reason is required.', $source);
        $this->assertStringContainsString(
            "'appointment_status_options' => json_decode(\$appointment_status_options, true) ?? []",
            $source,
        );
    }

    public function testSharedCancelHelperRequiresStatusSelect(): void
    {
        $source = file_get_contents($this->root . '/assets/js/utils/appointment_cancel.js');

        $this->assertStringContainsString('confirmAndCancel', $source);
        $this->assertStringContainsString('promptCancellationReason', $source);
        $this->assertStringContainsString('cancellation-reason', $source);
        $this->assertStringContainsString('select_cancellation_status', $source);
        $this->assertStringContainsString('cancellation_status_required', $source);
        $this->assertStringContainsString("vars('appointment_status_options')", $source);
    }

    public function testCalendarViewsUseSharedCancelHelper(): void
    {
        $default = file_get_contents($this->root . '/assets/js/utils/calendar_default_view.js');
        $table = file_get_contents($this->root . '/assets/js/utils/calendar_table_view.js');
        $modal = file_get_contents($this->root . '/assets/js/components/appointments_modal.js');
        $page = file_get_contents($this->root . '/application/views/pages/calendar.php');

        $this->assertStringContainsString('App.Utils.AppointmentCancel.confirmAndCancel', $default);
        $this->assertStringContainsString('App.Utils.AppointmentCancel.confirmAndCancel', $table);
        $this->assertStringContainsString('App.Utils.AppointmentCancel.confirmAndCancel', $modal);
        $this->assertStringContainsString('appointment_cancel.js', $page);
        $this->assertStringNotContainsString('deleteAppointment(appointmentId, null, false)', $default);
        $this->assertStringNotContainsString('deleteAppointment(appointmentId, null, false)', $table);
        $this->assertStringNotContainsString('deleteAppointment(appointmentId, null, false)', $modal);
    }

    public function testTranslationsCoverCancellationStatusPrompt(): void
    {
        $en = file_get_contents($this->root . '/application/language/english/translations_lang.php');
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        foreach (['select_appointment_cancellation_status', 'select_cancellation_status', 'cancellation_status_required'] as $key) {
            $this->assertStringContainsString("\$lang['{$key}']", $en);
            $this->assertStringContainsString("\$lang['{$key}']", $de);
        }
    }
}
