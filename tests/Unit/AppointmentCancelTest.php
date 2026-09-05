<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Soft-cancel appointment helpers.
 */
final class AppointmentCancelTest extends TestCase
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

        if (!defined('APPOINTMENT_STATUS_CANCELLED')) {
            define('APPOINTMENT_STATUS_CANCELLED', 'Cancelled');
        }

        if (!class_exists('EA_Model', false)) {
            eval('class EA_Model { public $db; }');
        }

        require_once dirname(__DIR__, 2) . '/application/models/Appointments_model.php';
        self::$loaded = true;
    }

    public function testIsCancelledRecognizesBothSpellings(): void
    {
        $model = (new ReflectionClass(\Appointments_model::class))->newInstanceWithoutConstructor();

        $this->assertTrue($model->is_cancelled(['status' => 'Cancelled']));
        $this->assertTrue($model->is_cancelled(['status' => 'canceled']));
        $this->assertTrue($model->is_cancelled(['status' => ' CANCELLED ']));
        $this->assertFalse($model->is_cancelled(['status' => 'Booked']));
        $this->assertFalse($model->is_cancelled(['status' => '']));
        $this->assertFalse($model->is_cancelled([]));
    }

    public function testCancelledStatusConstantMatchesCanonicalValue(): void
    {
        $this->assertSame('Cancelled', APPOINTMENT_STATUS_CANCELLED);
    }

    public function testSoftCancelMigrationExists(): void
    {
        $this->assertFileExists(
            dirname(__DIR__, 2) . '/application/migrations/074_add_soft_cancel_appointments.php',
        );
    }
}
