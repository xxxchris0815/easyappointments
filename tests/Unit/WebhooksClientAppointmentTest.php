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
        ]);

        $this->assertSame(7, $payload['id_users_created_by']);
        $this->assertSame('test', $payload['notes']);
        $this->assertArrayNotHasKey('id_users_secretary', $payload);
    }

    public function testPrepareAppointmentPayloadAllowsNullCreatedBy(): void
    {
        $payload = $this->client()->prepare_appointment_payload([
            'id' => 3,
            'id_users_created_by' => null,
        ]);

        $this->assertNull($payload['id_users_created_by']);
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
