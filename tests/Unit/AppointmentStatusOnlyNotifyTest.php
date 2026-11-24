<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Status-only appointment updates must not send change emails.
 */
class AppointmentStatusOnlyNotifyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testModelExposesNotifiableChangeHelper(): void
    {
        $source = file_get_contents($this->root . '/application/models/Appointments_model.php');

        $this->assertStringContainsString('function has_notifiable_change', $source);
        $this->assertStringContainsString('normalize_notifiable_value', $source);
        $this->assertStringContainsString("'start_datetime'", $source);
        $this->assertStringContainsString("'id_users_provider'", $source);
        // Status itself is intentionally not part of notifiable fields.
        $this->assertStringNotContainsString(
            "\$fields = [\n            'status'",
            $source,
        );
    }

    public function testCalendarAndApiSkipNotifyForStatusOnly(): void
    {
        $calendar = file_get_contents($this->root . '/application/controllers/Calendar.php');
        $api = file_get_contents($this->root . '/application/controllers/api/v1/Appointments_api_v1.php');

        $this->assertStringContainsString('has_notifiable_change', $calendar);
        $this->assertStringContainsString('$notify_users = false', $calendar);
        $this->assertStringContainsString('has_notifiable_change', $api);
    }

    public function testModalSkipsNotifyDialogForStatusOnly(): void
    {
        $modal = file_get_contents($this->root . '/assets/js/components/appointments_modal.js');
        $defaultView = file_get_contents($this->root . '/assets/js/utils/calendar_default_view.js');
        $tableView = file_get_contents($this->root . '/assets/js/utils/calendar_table_view.js');

        $this->assertStringContainsString('isStatusOnlyChange', $modal);
        $this->assertStringContainsString('setOriginalAppointment', $modal);
        $this->assertStringContainsString('isUpdate && isStatusOnlyChange(appointment)', $modal);
        $this->assertStringContainsString('setOriginalAppointment', $defaultView);
        $this->assertStringContainsString('setOriginalAppointment', $tableView);
    }

    public function testNotifiableChangeLogicMatchesExpectations(): void
    {
        $normalize = static function (string $field, mixed $value): string {
            if ($value === null) {
                return '';
            }

            if (in_array($field, ['id_users_provider', 'id_services', 'id_users_customer'], true)) {
                return (string) (int) $value;
            }

            if ($field === 'is_unavailability') {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            if (in_array($field, ['start_datetime', 'end_datetime'], true)) {
                return (new \DateTime((string) $value))->format('Y-m-d H:i:s');
            }

            return trim((string) $value);
        };

        $hasNotifiable = static function (array $before, array $after) use ($normalize): bool {
            $fields = [
                'start_datetime',
                'end_datetime',
                'id_users_provider',
                'id_services',
                'id_users_customer',
                'location',
                'meeting_link',
                'notes',
                'color',
                'is_unavailability',
            ];

            foreach ($fields as $field) {
                if ($normalize($field, $before[$field] ?? null) !== $normalize($field, $after[$field] ?? null)) {
                    return true;
                }
            }

            return false;
        };

        $base = [
            'id' => 1,
            'start_datetime' => '2026-11-04 10:00:00',
            'end_datetime' => '2026-11-04 11:00:00',
            'id_users_provider' => 9,
            'id_services' => 3,
            'id_users_customer' => 12,
            'location' => '',
            'meeting_link' => '',
            'notes' => '',
            'color' => '#FFFFFF',
            'is_unavailability' => 0,
            'status' => 'Booked',
        ];

        $statusOnly = $base;
        $statusOnly['status'] = 'No-show';
        $this->assertFalse($hasNotifiable($base, $statusOnly));

        $timeChanged = $base;
        $timeChanged['start_datetime'] = '2026-11-04 12:00:00';
        $timeChanged['end_datetime'] = '2026-11-04 13:00:00';
        $this->assertTrue($hasNotifiable($base, $timeChanged));
    }
}
