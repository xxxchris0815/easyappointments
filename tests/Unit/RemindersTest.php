<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Reminders;

/**
 * Appointment reminder rule helpers.
 */
final class RemindersTest extends TestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$loaded) {
            return;
        }

        if (!defined('BASEPATH')) {
            define('BASEPATH', true);
        }

        if (!class_exists('CI_Controller', false)) {
            eval('class CI_Controller {}');
        }

        if (!class_exists('EA_Controller', false)) {
            eval('class EA_Controller extends CI_Controller {}');
        }

        require_once dirname(__DIR__, 2) . '/application/libraries/Reminders.php';
        self::$loaded = true;
    }

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];
    }

    public function testIsEnabledRespectsSetting(): void
    {
        $GLOBALS['__ea_test_settings']['appointment_reminders_enabled'] = '0';
        $reminders = (new ReflectionClass(Reminders::class))->newInstanceWithoutConstructor();
        $this->assertFalse($reminders->is_enabled());

        $GLOBALS['__ea_test_settings']['appointment_reminders_enabled'] = '1';
        $this->assertTrue($reminders->is_enabled());
    }

    public function testGetRulesNormalizesValidEntries(): void
    {
        $GLOBALS['__ea_test_settings']['appointment_reminders'] = json_encode([
            ['id' => 'r1', 'offset' => 30, 'unit' => 'minutes', 'channels' => ['email', 'webhook', 'sms']],
            ['offset' => -1, 'unit' => 'hours', 'channels' => ['email']],
            ['id' => 'r2', 'offset' => 1, 'unit' => 'days', 'channels' => ['email']],
        ]);

        $reminders = (new ReflectionClass(Reminders::class))->newInstanceWithoutConstructor();
        $rules = $reminders->get_rules();

        $this->assertCount(2, $rules);
        $this->assertSame(['email', 'webhook'], $rules[0]['channels']);
        $this->assertSame('r2', $rules[1]['id']);
        $this->assertSame('days', $rules[1]['unit']);
    }

    public function testCalculateDueDatetimeSubtractsOffset(): void
    {
        $reminders = (new ReflectionClass(Reminders::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass($reminders))->getMethod('calculate_due_datetime');
        $method->setAccessible(true);

        $due = $method->invoke($reminders, '2026-08-20 15:00:00', 2, 'hours');
        $this->assertSame('2026-08-20 13:00:00', $due);

        $dueDays = $method->invoke($reminders, '2026-08-20 15:00:00', 1, 'days');
        $this->assertSame('2026-08-19 15:00:00', $dueDays);
    }
}
