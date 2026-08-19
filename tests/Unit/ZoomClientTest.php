<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zoom_client;

/**
 * Basic Zoom_client unit tests (enabled flag + public API).
 */
class ZoomClientTest extends TestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            require_once dirname(__DIR__, 2) . '/application/libraries/Zoom_client.php';
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '0',
            'zoom_account_id' => 'account',
            'zoom_client_id' => 'client',
            'zoom_client_secret' => 'secret',
        ];

        $client = new Zoom_client();

        $this->assertFalse($client->is_enabled());
    }

    public function testIsEnabledReturnsFalseWhenCredentialsMissing(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '1',
            'zoom_account_id' => '',
            'zoom_client_id' => 'client',
            'zoom_client_secret' => 'secret',
        ];

        $client = new Zoom_client();

        $this->assertFalse($client->is_enabled());
    }

    public function testIsEnabledReturnsTrueWhenConfigured(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '1',
            'zoom_account_id' => 'account',
            'zoom_client_id' => 'client',
            'zoom_client_secret' => 'secret',
        ];

        $client = new Zoom_client();

        $this->assertTrue($client->is_enabled());
    }

    public function testDeleteMeetingIsNoOpWhenDisabled(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '0',
        ];

        $client = new Zoom_client();
        $client->delete_meeting('12345');

        $this->assertTrue(true);
    }

    public function testSyncAppointmentReturnsExistingValuesWhenDisabled(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '0',
        ];

        $client = new Zoom_client();

        $result = $client->sync_appointment(
            [
                'id_zoom_meeting' => '999',
                'meeting_link' => 'https://zoom.example/j/999',
            ],
            [],
            [],
            [],
        );

        $this->assertSame('999', $result['id']);
        $this->assertSame('https://zoom.example/j/999', $result['join_url']);
        $this->assertFalse($result['success']);
    }

    public function testSyncAppointmentDoesNotThrowWhenProviderEmailMissing(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_enabled' => '1',
            'zoom_account_id' => 'account',
            'zoom_client_id' => 'client',
            'zoom_client_secret' => 'secret',
        ];

        $client = new Zoom_client();

        $result = $client->sync_appointment(
            [
                'start_datetime' => '2026-08-21 09:00:00',
                'end_datetime' => '2026-08-21 10:00:00',
            ],
            ['email' => '', 'settings' => []],
            ['name' => 'Test'],
            [],
        );

        $this->assertNull($result['id']);
        $this->assertNull($result['join_url']);
        $this->assertFalse($result['success']);
    }

    public function testClassExposesExpectedPublicApi(): void
    {
        $reflection = new ReflectionClass(Zoom_client::class);

        $this->assertTrue($reflection->hasMethod('is_enabled'));
        $this->assertTrue($reflection->hasMethod('sync_appointment'));
        $this->assertTrue($reflection->hasMethod('delete_meeting'));
    }
}
