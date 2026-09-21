<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for Google Calendar sync status admin page.
 */
class GoogleCalendarSyncStatusSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testControllerAndViewExist(): void
    {
        $this->assertFileExists($this->root . '/application/controllers/Google_calendar_sync_status.php');
        $this->assertFileExists($this->root . '/application/views/pages/google_calendar_sync_status.php');
        $this->assertFileExists($this->root . '/assets/js/pages/google_calendar_sync_status.js');
        $this->assertFileExists($this->root . '/assets/js/http/google_calendar_sync_status_http_client.js');
    }

    public function testControllerExposesProvidersAndLogsEndpoints(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Google_calendar_sync_status.php');

        $this->assertStringContainsString('function providers', $source);
        $this->assertStringContainsString('function logs', $source);
        $this->assertStringContainsString("'connected' => \$connected", $source);
        $this->assertStringContainsString('read_google_log_lines', $source);
        // Token may be inspected server-side, but must not be returned in response rows.
        $this->assertStringNotContainsString("'google_token'", $source);
        $this->assertStringNotContainsString('"google_token"', $source);
    }

    public function testIntegrationsLinksToSyncStatus(): void
    {
        $source = file_get_contents($this->root . '/application/views/pages/integrations.php');
        $this->assertStringContainsString('google_calendar_sync_status', $source);
    }

    public function testTranslationsExist(): void
    {
        foreach (['english', 'german'] as $locale) {
            $source = file_get_contents(
                $this->root . '/application/language/' . $locale . '/translations_lang.php',
            );
            $this->assertStringContainsString("\$lang['google_calendar_sync_status']", $source, $locale);
            $this->assertStringContainsString("\$lang['google_sync_logs']", $source, $locale);
            $this->assertStringContainsString("\$lang['google_connected']", $source, $locale);
        }
    }
}
