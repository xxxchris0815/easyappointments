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

    public function testPrepareAppointmentPayloadCastsOwnershipIds(): void
    {
        $client = (new ReflectionClass(Webhooks_client::class))->newInstanceWithoutConstructor();

        $payload = $client->prepare_appointment_payload([
            'id' => '10',
            'id_users_created_by' => '7',
            'id_users_secretary' => '7',
            'notes' => 'test',
        ]);

        $this->assertSame(7, $payload['id_users_created_by']);
        $this->assertSame(7, $payload['id_users_secretary']);
        $this->assertSame('test', $payload['notes']);
    }

    public function testPrepareAppointmentPayloadDefaultsMissingSecretaryId(): void
    {
        $client = (new ReflectionClass(Webhooks_client::class))->newInstanceWithoutConstructor();

        $payload = $client->prepare_appointment_payload([
            'id' => 3,
            'id_users_created_by' => null,
        ]);

        $this->assertNull($payload['id_users_created_by']);
        $this->assertNull($payload['id_users_secretary']);
    }

    public function testWebhookActionConstantsAreDistinct(): void
    {
        $this->assertNotSame(WEBHOOK_APPOINTMENT_CREATE, WEBHOOK_APPOINTMENT_UPDATE);
        $this->assertNotSame(WEBHOOK_APPOINTMENT_CREATE, WEBHOOK_APPOINTMENT_SAVE);
        $this->assertSame('appointment_create', WEBHOOK_APPOINTMENT_CREATE);
        $this->assertSame('appointment_update', WEBHOOK_APPOINTMENT_UPDATE);
    }
}
