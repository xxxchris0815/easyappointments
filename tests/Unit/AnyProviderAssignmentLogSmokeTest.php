<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for Any Provider assignment log / calculation admin page.
 */
class AnyProviderAssignmentLogSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testControllerViewAndAssetsExist(): void
    {
        $this->assertFileExists($this->root . '/application/controllers/Any_provider_assignment_log.php');
        $this->assertFileExists($this->root . '/application/views/pages/any_provider_assignment_log.php');
        $this->assertFileExists($this->root . '/assets/js/pages/any_provider_assignment_log.js');
        $this->assertFileExists($this->root . '/assets/js/http/any_provider_assignment_log_http_client.js');
    }

    public function testLibraryExposesExplainAndLogging(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Any_provider_assignment.php');

        $this->assertStringContainsString('function explain', $source);
        $this->assertStringContainsString('function read_logs', $source);
        $this->assertStringContainsString('function build_report', $source);
        $this->assertStringContainsString('dry_run: false', $source);
        $this->assertStringContainsString('dry_run: true', $source);
        $this->assertStringContainsString('any_provider_assignment.log', $source);
        $this->assertStringContainsString('preferred_provider_still_available', $source);
        $this->assertStringContainsString('does_not_offer_service', $source);
        $this->assertStringContainsString('slot_unavailable', $source);
        $this->assertStringContainsString('counter_advanced', $source);
        $this->assertStringContainsString("'summary'", $source);
        $this->assertStringContainsString('Only candidate available', $source);
        $this->assertStringContainsString('function candidate_label', $source);
    }

    public function testControllerExposesExplainAndLogsEndpoints(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Any_provider_assignment_log.php');

        $this->assertStringContainsString('function explain', $source);
        $this->assertStringContainsString('function logs', $source);
        $this->assertStringContainsString('PRIV_SYSTEM_SETTINGS', $source);
        $this->assertStringContainsString('->explain(', $source);
        $this->assertStringContainsString('read_logs', $source);
    }

    public function testUiWiresSimulationAndLogs(): void
    {
        $view = file_get_contents($this->root . '/application/views/pages/any_provider_assignment_log.php');
        $js = file_get_contents($this->root . '/assets/js/pages/any_provider_assignment_log.js');
        $http = file_get_contents($this->root . '/assets/js/http/any_provider_assignment_log_http_client.js');

        $this->assertStringContainsString('any-provider-explain-form', $view);
        $this->assertStringContainsString('any-provider-providers-table', $view);
        $this->assertStringContainsString('any-provider-logs-table', $view);
        $this->assertStringContainsString('any-provider-last-result', $view);
        $this->assertStringContainsString('any_provider_assignment_log/explain', $http);
        $this->assertStringContainsString('any_provider_assignment_log/logs', $http);
        $this->assertStringContainsString('runExplain', $js);
        $this->assertStringContainsString('loadLogs', $js);
        $this->assertStringContainsString('formatSelectionReason', $js);
        $this->assertStringContainsString('basis.summary', $js);
    }

    public function testBookingSettingsLinksToLog(): void
    {
        $source = file_get_contents($this->root . '/application/views/pages/booking_settings.php');
        $this->assertStringContainsString('any_provider_assignment_log', $source);
        $this->assertStringContainsString('any_provider_assignment_log_open', $source);
    }

    public function testTranslationsExist(): void
    {
        foreach (['english', 'german'] as $locale) {
            $source = file_get_contents(
                $this->root . '/application/language/' . $locale . '/translations_lang.php',
            );
            $this->assertStringContainsString("\$lang['any_provider_assignment_log']", $source, $locale);
            $this->assertStringContainsString("\$lang['any_provider_run_calculation']", $source, $locale);
            $this->assertStringContainsString("\$lang['any_provider_recent_assignments']", $source, $locale);
            $this->assertStringContainsString("\$lang['any_provider_rr_counter']", $source, $locale);
        }
    }

    public function testExistingPreferredProviderBehaviorPreserved(): void
    {
        $lib = file_get_contents($this->root . '/application/libraries/Any_provider_assignment.php');
        $booking = file_get_contents($this->root . '/application/controllers/Booking.php');

        $this->assertStringContainsString('?int $preferred_provider_id = null', $lib);
        $this->assertStringContainsString("selection_method = 'preferred_provider'", $lib);
        $this->assertStringContainsString('$preferred_provider_id', $booking);
    }
}
