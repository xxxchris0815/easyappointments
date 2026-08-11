<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Lightweight smoke checks for custom fork helpers/config assumptions.
 */
final class ModuleSmokeTest extends TestCase
{
    public function testMaxCustomFieldsConstantExists(): void
    {
        if (!defined('MAX_CUSTOM_FIELDS')) {
            require_once dirname(__DIR__, 2) . '/application/config/constants.php';
        }

        $this->assertSame(20, MAX_CUSTOM_FIELDS);
    }

    public function testZoomClientLibraryExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/application/libraries/Zoom_client.php');
        $this->assertFileExists(dirname(__DIR__, 2) . '/application/controllers/Zoom_settings.php');
    }

    public function testBookingTrackerLibraryExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/application/libraries/Booking_tracker.php');
    }

    public function testMigration070Exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/application/migrations/070_add_custom_fork_features.php');
    }
}
