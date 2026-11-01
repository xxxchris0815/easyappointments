<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Mautic_client;

/**
 * Basic Mautic_client unit tests (config + API normalization).
 */
class MauticClientTest extends TestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            require_once dirname(__DIR__, 2) . '/application/libraries/Mautic_client.php';
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];
    }

    public function testIsConfiguredReturnsFalseWhenDisabled(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'mautic_lead_lookup_enabled' => '0',
            'mautic_lookup_mode' => 'api',
            'mautic_api_url' => 'https://mailings.example.com',
            'mautic_api_username' => 'user',
            'mautic_api_password' => 'secret',
        ];

        $client = new Mautic_client();

        $this->assertFalse($client->is_configured());
    }

    public function testIsConfiguredReturnsTrueForApiCredentials(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'mautic_lead_lookup_enabled' => '1',
            'mautic_lookup_mode' => 'api',
            'mautic_api_url' => 'https://mailings.example.com',
            'mautic_api_username' => 'user',
            'mautic_api_password' => 'secret',
        ];

        $client = new Mautic_client();

        $this->assertTrue($client->is_configured());
        $this->assertTrue($client->has_api_credentials());
    }

    public function testIsConfiguredReturnsTrueForWebhookMode(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'mautic_lead_lookup_enabled' => '1',
            'mautic_lookup_mode' => 'webhook',
            'mautic_lead_lookup_url' => 'https://example.com/webhook',
        ];

        $client = new Mautic_client();

        $this->assertTrue($client->is_configured());
    }

    public function testLookupByIdRejectsNonNumericLeadId(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'mautic_lead_lookup_enabled' => '1',
            'mautic_lookup_mode' => 'api',
            'mautic_api_url' => 'https://mailings.example.com',
            'mautic_api_username' => 'user',
            'mautic_api_password' => 'secret',
        ];

        $client = new Mautic_client();

        $this->expectException(\InvalidArgumentException::class);
        $client->lookup_by_id('not-a-number');
    }

    public function testNormalizeApiContactMapsCoreFields(): void
    {
        $client = new Mautic_client();
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('normalize_api_contact');
        $method->setAccessible(true);

        $payload = [
            'contact' => [
                'fields' => [
                    'core' => [
                        'firstname' => ['value' => 'Ada'],
                        'lastname' => ['value' => 'Lovelace'],
                        'email' => ['value' => 'ada@example.com'],
                        'mobile' => ['value' => '+491234'],
                    ],
                ],
            ],
        ];

        $result = $method->invoke($client, $payload, '42');

        $this->assertSame(
            [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
                'phone_number' => '+491234',
                'l_id' => '42',
            ],
            $result,
        );
    }
}
