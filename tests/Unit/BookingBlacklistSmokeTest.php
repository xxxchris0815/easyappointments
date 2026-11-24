<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for booking blacklist feature.
 */
class BookingBlacklistSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testFilesExist(): void
    {
        $this->assertFileExists($this->root . '/application/libraries/Booking_blacklist.php');
        $this->assertFileExists($this->root . '/application/controllers/Booking_blacklist_settings.php');
        $this->assertFileExists($this->root . '/application/views/pages/booking_blacklist_settings.php');
        $this->assertFileExists($this->root . '/assets/js/pages/booking_blacklist_settings.js');
        $this->assertFileExists($this->root . '/assets/js/http/booking_blacklist_settings_http_client.js');
        $this->assertFileExists($this->root . '/application/migrations/091_add_booking_blacklist_setting.php');
    }

    public function testLibrarySupportsEmailPhoneAndMauticModes(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Booking_blacklist.php');

        $this->assertStringContainsString("TYPE_EMAIL = 'email'", $source);
        $this->assertStringContainsString("TYPE_PHONE = 'phone'", $source);
        $this->assertStringContainsString("TYPE_MAUTIC_ID = 'mautic_id'", $source);
        $this->assertStringContainsString('PHONE_MATCH_WITH_COUNTRY', $source);
        $this->assertStringContainsString('PHONE_MATCH_WITHOUT_COUNTRY', $source);
        $this->assertStringContainsString('function match', $source);
        $this->assertStringContainsString('function report_blocked_attempt', $source);
        $this->assertStringContainsString('notify_admins', $source);
        $this->assertStringContainsString('booking_blacklist.log', $source);
    }

    public function testBookingRegisterChecksBlacklist(): void
    {
        $booking = file_get_contents($this->root . '/application/controllers/Booking.php');
        $js = file_get_contents($this->root . '/assets/js/pages/booking.js');

        $this->assertStringContainsString('booking_blacklist', $booking);
        $this->assertStringContainsString('report_blocked_attempt', $booking);
        $this->assertStringContainsString('booking_blacklist_blocked_message', $booking);
        $this->assertStringContainsString("data.l_id = String(mauticLeadId)", $js);
    }

    public function testAdminUiWired(): void
    {
        $controller = file_get_contents($this->root . '/application/controllers/Booking_blacklist_settings.php');
        $view = file_get_contents($this->root . '/application/views/pages/booking_blacklist_settings.php');
        $nav = file_get_contents($this->root . '/application/views/components/settings_nav.php');
        $settings = file_get_contents($this->root . '/application/views/pages/booking_settings.php');

        $this->assertStringContainsString('function save', $controller);
        $this->assertStringContainsString('function logs', $controller);
        $this->assertStringContainsString('PRIV_SYSTEM_SETTINGS', $controller);
        $this->assertStringContainsString('booking-blacklist-entries', $view);
        $this->assertStringContainsString('booking_blacklist_settings', $nav);
        $this->assertStringContainsString('booking_blacklist_settings', $settings);
    }

    public function testTranslationsExist(): void
    {
        foreach (['english', 'german'] as $locale) {
            $source = file_get_contents(
                $this->root . '/application/language/' . $locale . '/translations_lang.php',
            );
            $this->assertStringContainsString("\$lang['booking_blacklist']", $source, $locale);
            $this->assertStringContainsString("\$lang['booking_blacklist_phone_with_country']", $source, $locale);
            $this->assertStringContainsString("\$lang['booking_blacklist_blocked_message']", $source, $locale);
        }
    }
}
