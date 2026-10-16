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
        $this->assertTrue($reflection->hasMethod('build_meeting_topic'));
        $this->assertTrue($reflection->hasMethod('available_topic_placeholders'));
        $this->assertTrue($reflection->hasMethod('delete_meeting'));
    }

    public function testBuildMeetingTopicUsesDefaultTemplate(): void
    {
        $GLOBALS['__ea_test_settings'] = [
            'zoom_meeting_topic_template' => '',
        ];

        $client = new Zoom_client();

        $topic = $client->build_meeting_topic(
            ['start_datetime' => '2026-10-16 11:00:00', 'end_datetime' => '2026-10-16 12:00:00'],
            ['name' => 'Herz-zu-Herz Gespräch'],
            ['first_name' => 'Lory', 'last_name' => 'Glocke'],
            ['first_name' => 'Anna', 'email' => 'anna@example.com'],
        );

        $this->assertSame('Herz-zu-Herz Gespräch - Anna - anna@example.com', $topic);
    }

    public function testBuildMeetingTopicHonorsCustomTemplate(): void
    {
        $client = new Zoom_client();

        $topic = $client->build_meeting_topic(
            ['id' => 42, 'start_datetime' => '2026-10-16 11:00:00', 'end_datetime' => '2026-10-16 12:00:00'],
            ['name' => 'Coaching'],
            ['first_name' => 'Lory', 'last_name' => 'Glocke'],
            ['first_name' => 'Max', 'last_name' => 'Mustermann', 'email' => 'max@example.com'],
            '{provider} | {customer_name} | {service} #{appointment_id}',
        );

        $this->assertSame('Lory Glocke | Max Mustermann | Coaching #42', $topic);
    }

    public function testBuildMeetingTopicOmitsEmptyPlaceholderSegments(): void
    {
        $client = new Zoom_client();

        $topic = $client->build_meeting_topic(
            [],
            ['name' => 'Coaching'],
            [],
            ['first_name' => '', 'email' => 'max@example.com'],
            '{service} - {customer_first_name} - {customer_email}',
        );

        $this->assertSame('Coaching - max@example.com', $topic);
    }

    public function testBuildMeetingTopicFallsBackWhenEverythingEmpty(): void
    {
        $client = new Zoom_client();

        $this->assertSame(
            'Appointment',
            $client->build_meeting_topic([], [], [], [], '{service} - {customer_email}'),
        );
    }

    public function testBuildMeetingTopicTruncatesToZoomLimit(): void
    {
        $client = new Zoom_client();

        $topic = $client->build_meeting_topic(
            [],
            ['name' => str_repeat('A', 120)],
            [],
            ['first_name' => str_repeat('B', 50), 'email' => str_repeat('c', 40) . '@example.com'],
            '{service} - {customer_first_name} - {customer_email}',
        );

        $this->assertLessThanOrEqual(200, mb_strlen($topic));
        $this->assertStringEndsWith('...', $topic);
    }

    public function testZoomSettingsPageWiresTopicTemplate(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/application/controllers/Zoom_settings.php');
        $view = file_get_contents($root . '/application/views/pages/zoom_settings.php');
        $en = file_get_contents($root . '/application/language/english/translations_lang.php');
        $de = file_get_contents($root . '/application/language/german/translations_lang.php');
        $migration = file_get_contents($root . '/application/migrations/090_add_zoom_meeting_topic_template.php');

        $this->assertStringContainsString('zoom_meeting_topic_template', $controller);
        $this->assertStringContainsString('data-field="zoom_meeting_topic_template"', $view);
        $this->assertStringContainsString("\$lang['zoom_meeting_topic_template']", $en);
        $this->assertStringContainsString("\$lang['zoom_meeting_topic_template']", $de);
        $this->assertStringContainsString('zoom_meeting_topic_template', $migration);
        $this->assertStringContainsString('{service} - {customer_first_name} - {customer_email}', $migration);
    }
}
