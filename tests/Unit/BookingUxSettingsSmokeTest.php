<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for booking UX, SMTP, custom CSS and statistics additions.
 */
final class BookingUxSettingsSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMigration076Exists(): void
    {
        $this->assertFileExists(
            $this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php',
        );
    }

    public function testSmtpAndStatisticsControllersExist(): void
    {
        $this->assertFileExists($this->root . '/application/controllers/Smtp_settings.php');
        $this->assertFileExists($this->root . '/application/controllers/Appointment_statistics.php');
        $this->assertFileExists($this->root . '/application/views/pages/smtp_settings.php');
        $this->assertFileExists($this->root . '/application/views/pages/appointment_statistics.php');
    }

    public function testCustomCssComponentExists(): void
    {
        $this->assertFileExists($this->root . '/application/views/components/custom_css_style.php');
        $layout = file_get_contents($this->root . '/application/views/layouts/booking_layout.php');
        $this->assertStringContainsString("component('custom_css_style')", $layout);
    }

    public function testBookingJsTracksFieldLevelEventsAndSkipConfirmation(): void
    {
        $js = file_get_contents($this->root . '/assets/js/pages/booking.js');

        $this->assertNotFalse($js);
        $this->assertStringContainsString('trackBookingProgressDebounced', $js);
        $this->assertStringContainsString("trackBookingProgress('date_selected'", $js);
        $this->assertStringContainsString("trackBookingProgress('time_selected')", $js);
        $this->assertStringContainsString('booking_skip_confirmation_step', $js);
        $this->assertStringContainsString('submitBookingWithoutConfirmation', $js);
    }

    public function testGermanTranslationsCoverNewKeys(): void
    {
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        $this->assertNotFalse($de);
        $this->assertStringContainsString("\$lang['smtp_settings']", $de);
        $this->assertStringContainsString("\$lang['appointment_statistics']", $de);
        $this->assertStringContainsString("\$lang['custom_css_enabled']", $de);
        $this->assertStringContainsString("\$lang['booking_skip_confirmation_step']", $de);
        $this->assertStringContainsString("\$lang['booking_end_screen_title']", $de);
    }

    public function testEmailMessagesPrefersBackendSmtpSettings(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Email_messages.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("setting('smtp_enabled'", $source);
        $this->assertStringContainsString("setting('smtp_host'", $source);
    }

    public function testDefaultCustomCssDoesNotHideCustomFields(): void
    {
        $source = file_get_contents(
            $this->root . '/application/migrations/076_add_booking_ux_smtp_stats_settings.php',
        );

        $this->assertNotFalse($source);
        $this->assertStringContainsString('--gold-primary', $source);
        $this->assertStringNotContainsString('custom-field-1', $source);
        $this->assertStringContainsString('Custom fields stay visible', $source);
    }
}
