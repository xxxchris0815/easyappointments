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

    public function testControllerExposesProvidersLogsAndResetEndpoints(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Google_calendar_sync_status.php');

        $this->assertStringContainsString('function providers', $source);
        $this->assertStringContainsString('function logs', $source);
        $this->assertStringContainsString('function reset_unavailabilities', $source);
        $this->assertStringContainsString('function diagnose', $source);
        $this->assertStringContainsString('delete_google_unavailabilities', $source);
        $this->assertStringContainsString('dedupe_exact_unavailability_slots', $source);
        $this->assertStringContainsString('collapse_nested_blank_manual_unavailabilities', $source);
        $this->assertStringContainsString('remove_unavailable_events', $source);
        $this->assertStringContainsString('duplicate_slot_groups', $source);
        $this->assertStringContainsString('Google::run_sync', $source);
        $this->assertStringContainsString("'connected' => \$connected", $source);
        $this->assertStringContainsString('google_unavailability_count', $source);
        $this->assertStringContainsString('read_google_log_lines', $source);

        // Response row construction should expose connected flag, not the raw token field.
        $row_block_start = strpos($source, '$rows[] = [');
        $this->assertNotFalse($row_block_start);
        $row_block = substr($source, $row_block_start, 600);
        $this->assertStringContainsString("'connected' => \$connected", $row_block);
        $this->assertStringNotContainsString('google_token', $row_block);

        // Reset must only remove Google-sourced unavailabilities.
        $this->assertStringContainsString("empty(\$row['id_google_calendar'])", $source);
        $this->assertStringContainsString('Keep manual EA unavailabilities', $source);
    }

    public function testUiWiresResetButton(): void
    {
        $view = file_get_contents($this->root . '/application/views/pages/google_calendar_sync_status.php');
        $js = file_get_contents($this->root . '/assets/js/pages/google_calendar_sync_status.js');
        $http = file_get_contents($this->root . '/assets/js/http/google_calendar_sync_status_http_client.js');

        $this->assertStringContainsString('google_reset_unavailabilities_info', $view);
        $this->assertStringContainsString("lang('actions')", $view);
        $this->assertStringContainsString('google-sync-last-result', $view);
        $this->assertStringContainsString('resetUnavailabilities', $js);
        $this->assertStringContainsString('diagnoseUnavailabilities', $js);
        $this->assertStringContainsString('google-reset-unavailabilities', $js);
        $this->assertStringContainsString('google-diagnose-unavailabilities', $js);
        $this->assertStringContainsString('reset_unavailabilities', $http);
        $this->assertStringContainsString('diagnose', $http);
    }

    public function testGoogleSyncExposesRunSyncHelper(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Google.php');

        $this->assertStringContainsString('function run_sync', $source);
        $this->assertStringContainsString('self::run_sync($provider_id)', $source);
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
            $this->assertStringContainsString("\$lang['google_reset_unavailabilities']", $source, $locale);
            $this->assertStringContainsString("\$lang['google_diagnose_unavailabilities']", $source, $locale);
            $this->assertStringContainsString("\$lang['google_unavailabilities_reset_success']", $source, $locale);
        }
    }

    public function testDocsDescribeReset(): void
    {
        $docs = file_get_contents($this->root . '/docs/google-calendar-sync.md');
        $this->assertStringContainsString('Reset & re-sync', $docs);
        $this->assertStringContainsString('id_google_calendar', $docs);
        $this->assertStringContainsString('Unavailable', $docs);
    }
}
