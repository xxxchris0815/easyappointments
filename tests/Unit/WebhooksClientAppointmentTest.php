<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webhooks_client;

/**
 * Webhooks client appointment create/update helpers.
 */
class WebhooksClientAppointmentTest extends TestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            if (!defined('WEBHOOK_APPOINTMENT_SAVE')) {
                define('WEBHOOK_APPOINTMENT_SAVE', 'appointment_save');
            }
            if (!defined('WEBHOOK_APPOINTMENT_CREATE')) {
                define('WEBHOOK_APPOINTMENT_CREATE', 'appointment_create');
            }
            if (!defined('WEBHOOK_APPOINTMENT_UPDATE')) {
                define('WEBHOOK_APPOINTMENT_UPDATE', 'appointment_update');
            }
            if (!defined('WEBHOOK_APPOINTMENT_DELETE')) {
                define('WEBHOOK_APPOINTMENT_DELETE', 'appointment_delete');
            }

            require_once dirname(__DIR__, 2) . '/application/libraries/Webhooks_client.php';
            self::$loaded = true;
        }
    }

    private function client(): Webhooks_client
    {
        return (new ReflectionClass(Webhooks_client::class))->newInstanceWithoutConstructor();
    }

    public function testPrepareAppointmentPayloadCastsCreatedById(): void
    {
        $payload = $this->client()->prepare_appointment_payload([
            'id' => '10',
            'id_users_created_by' => '7',
            'notes' => 'test',
            'hash' => 'abc123XYZ789',
        ]);

        $this->assertSame(7, $payload['id_users_created_by']);
        $this->assertSame('test', $payload['notes']);
        $this->assertArrayNotHasKey('id_users_secretary', $payload);
        $this->assertSame('booking/reschedule/abc123XYZ789', $payload['modify_link']);
        $this->assertSame('booking_cancellation/of/abc123XYZ789', $payload['cancel_link']);
    }

    public function testPrepareAppointmentPayloadAllowsNullCreatedBy(): void
    {
        $payload = $this->client()->prepare_appointment_payload([
            'id' => 3,
            'id_users_created_by' => null,
        ]);

        $this->assertNull($payload['id_users_created_by']);
        $this->assertNull($payload['modify_link']);
        $this->assertNull($payload['cancel_link']);
    }

    public function testBuildAppointmentLinksUsesHash(): void
    {
        $links = $this->client()->build_appointment_links(['hash' => 'hashValue12']);

        $this->assertSame('booking/reschedule/hashValue12', $links['modify_link']);
        $this->assertSame('booking_cancellation/of/hashValue12', $links['cancel_link']);
    }

    public function testDiffAppointmentFieldsReturnsOnlyChanges(): void
    {
        $previous = [
            'id' => 5,
            'hash' => 'sameHash1234',
            'start_datetime' => '2026-08-01 10:00:00',
            'end_datetime' => '2026-08-01 11:00:00',
            'notes' => 'old',
            'status' => 'Booked',
            'update_datetime' => '2026-08-01 09:00:00',
            'id_users_provider' => '2',
        ];

        $current = [
            'id' => 5,
            'hash' => 'sameHash1234',
            'start_datetime' => '2026-08-01 12:00:00',
            'end_datetime' => '2026-08-01 13:00:00',
            'notes' => 'old',
            'status' => 'Booked',
            'update_datetime' => '2026-08-01 11:00:00',
            'id_users_provider' => 2,
        ];

        $changes = $this->client()->diff_appointment_fields($previous, $current);

        $this->assertArrayHasKey('start_datetime', $changes);
        $this->assertArrayHasKey('end_datetime', $changes);
        $this->assertArrayNotHasKey('notes', $changes);
        $this->assertArrayNotHasKey('hash', $changes);
        $this->assertArrayNotHasKey('update_datetime', $changes);
        $this->assertArrayNotHasKey('id_users_provider', $changes);
        $this->assertSame(
            ['from' => '2026-08-01 10:00:00', 'to' => '2026-08-01 12:00:00'],
            $changes['start_datetime'],
        );
    }

    public function testPrepareAppointmentUpdatePayloadKeepsIdentityAndChanges(): void
    {
        $previous = [
            'id' => 9,
            'hash' => 'updHashValue1',
            'notes' => 'before',
            'status' => 'Booked',
            'update_datetime' => '2026-08-01 09:00:00',
        ];

        $current = [
            'id' => 9,
            'hash' => 'updHashValue1',
            'notes' => 'after',
            'status' => 'Booked',
            'update_datetime' => '2026-08-01 10:00:00',
            'id_users_created_by' => null,
        ];

        $payload = $this->client()->prepare_appointment_update_payload($current, $previous);

        $this->assertSame(9, $payload['id']);
        $this->assertSame('updHashValue1', $payload['hash']);
        $this->assertSame('booking/reschedule/updHashValue1', $payload['modify_link']);
        $this->assertSame('booking_cancellation/of/updHashValue1', $payload['cancel_link']);
        $this->assertSame(['notes'], $payload['changed_fields']);
        $this->assertSame(['from' => 'before', 'to' => 'after'], $payload['changes']['notes']);
        $this->assertArrayNotHasKey('status', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
    }

    public function testWebhookActionConstantsAreDistinct(): void
    {
        $this->assertNotSame(WEBHOOK_APPOINTMENT_CREATE, WEBHOOK_APPOINTMENT_UPDATE);
        $this->assertNotSame(WEBHOOK_APPOINTMENT_CREATE, WEBHOOK_APPOINTMENT_SAVE);
        $this->assertSame('appointment_create', WEBHOOK_APPOINTMENT_CREATE);
        $this->assertSame('appointment_update', WEBHOOK_APPOINTMENT_UPDATE);
    }

    public function testResolveAppointmentSavedActionPrefersCreateOverSave(): void
    {
        $client = $this->client();

        $this->assertSame(
            WEBHOOK_APPOINTMENT_CREATE,
            $client->resolve_appointment_saved_action(
                ['appointment_create', 'appointment_save', 'appointment_update'],
                false,
            ),
        );
    }

    public function testResolveAppointmentSavedActionPrefersUpdateOverSave(): void
    {
        $client = $this->client();

        $this->assertSame(
            WEBHOOK_APPOINTMENT_UPDATE,
            $client->resolve_appointment_saved_action(
                ['appointment_create', 'appointment_save', 'appointment_update'],
                true,
            ),
        );
    }

    public function testResolveAppointmentSavedActionFallsBackToLegacySave(): void
    {
        $client = $this->client();

        $this->assertSame(
            WEBHOOK_APPOINTMENT_SAVE,
            $client->resolve_appointment_saved_action(['appointment_save'], false),
        );
    }

    public function testResolveAppointmentSavedActionReturnsNullWhenUnrelated(): void
    {
        $client = $this->client();

        $this->assertNull(
            $client->resolve_appointment_saved_action(['appointment_delete', 'customer_save'], false),
        );
    }
}
