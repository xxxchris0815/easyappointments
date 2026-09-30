<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for bidirectional Google Calendar sync wiring.
 */
class GoogleBidirectionalSyncSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testGoogleSyncDefinesOriginMetadataHelpers(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Google_sync.php');

        $this->assertStringContainsString('EA_EXTENDED_APPOINTMENT_ID', $source);
        $this->assertStringContainsString('function apply_ea_origin_metadata', $source);
        $this->assertStringContainsString('function get_ea_appointment_id_from_event', $source);
        $this->assertStringContainsString('function is_ea_origin_event', $source);
        $this->assertStringContainsString('apply_ea_origin_metadata($event', $source);
    }

    public function testGoogleSyncImportsForeignEventsAsUnavailabilities(): void
    {
        $source = file_get_contents($this->root . '/application/controllers/Google.php');

        $this->assertStringContainsString('function run_sync', $source);
        $this->assertStringContainsString('Phase 2: import foreign Google events as Unavailabilities', $source);
        $this->assertStringContainsString('is_ea_origin_event', $source);
        $this->assertStringContainsString('expand that manual block to the union', $source);
        $this->assertStringContainsString('expanded_overlap', $source);
        $this->assertStringContainsString('existing_unavailabilities[]', $source);
        $this->assertStringContainsString('overlapping_manual_indexes', $source);
        $this->assertStringContainsString('all-day day-flip bug', $source);
        $this->assertStringContainsString('is_synthetic_unavailable_event', $source);
        $this->assertStringContainsString('is_free_event', $source);
        $this->assertStringContainsString('skipped_transparent', $source);
        $this->assertStringContainsString("\$provider['timezone']", $source);
    }

    public function testGoogleSyncPassesTimezoneForAllDayEvents(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Google_sync.php');

        $this->assertStringContainsString("\$params['timeZone'] = \$timezone", $source);
        $this->assertStringContainsString('Prefer the date-only fields when present', $source);
        $this->assertStringContainsString("modify('-1 second')", $source);
    }

    public function testSynchronizationDoesNotPushUnavailabilitiesToGoogle(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Synchronization.php');

        $this->assertStringContainsString('do not push Unavailabilities', $source);
        $this->assertStringNotContainsString('add_unavailability(', $source);
        $this->assertStringNotContainsString('update_unavailability(', $source);
        $this->assertStringNotContainsString('delete_unavailability(', $source);
    }

    public function testDocsDescribeBidirectionalSync(): void
    {
        $docs = file_get_contents($this->root . '/docs/google-calendar-sync.md');

        $this->assertStringContainsString('EA → Google', $docs);
        $this->assertStringContainsString('Google → EA', $docs);
        $this->assertStringContainsString('never pushed back', $docs);
        $this->assertStringContainsString('ea_appointment_id', $docs);
        $this->assertStringContainsString('Show as: Free', $docs);
        $this->assertStringContainsString('When does a sync run?', $docs);
        $this->assertStringContainsString('sync_due', $docs);
        $this->assertStringContainsString('Automatic Sync', $docs);
        $this->assertStringContainsString('All-day', $docs);
        $this->assertStringContainsString('flip between days', $docs);
    }
}
