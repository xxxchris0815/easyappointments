<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Google unavailable leftover detection and range overlap.
 */
class GoogleUnavailableLeftoverTest extends TestCase
{
    private object $stub;

    protected function setUp(): void
    {
        $this->stub = new class {
            public function is_synthetic_unavailable_event($event): bool
            {
                if (!is_object($event) || !method_exists($event, 'getSummary')) {
                    return false;
                }

                return strcasecmp(trim((string) $event->getSummary()), 'Unavailable') === 0;
            }

            public function ranges_overlap(int $start_a, int $end_a, int $start_b, int $end_b): bool
            {
                return $start_a < $end_b && $end_a > $start_b;
            }
        };
    }

    private function eventWithSummary(string $summary): object
    {
        return new class ($summary) {
            public function __construct(private string $summary)
            {
            }

            public function getSummary(): string
            {
                return $this->summary;
            }
        };
    }

    public function testDetectsSyntheticUnavailableLeftovers(): void
    {
        $this->assertTrue($this->stub->is_synthetic_unavailable_event($this->eventWithSummary('Unavailable')));
        $this->assertTrue($this->stub->is_synthetic_unavailable_event($this->eventWithSummary('unavailable')));
        $this->assertTrue($this->stub->is_synthetic_unavailable_event($this->eventWithSummary('  Unavailable  ')));
        $this->assertFalse($this->stub->is_synthetic_unavailable_event($this->eventWithSummary('Meeting')));
        $this->assertFalse($this->stub->is_synthetic_unavailable_event($this->eventWithSummary('Unavailable overtime')));
    }

    public function testRangesOverlap(): void
    {
        $this->assertTrue($this->stub->ranges_overlap(100, 200, 150, 250));
        $this->assertTrue($this->stub->ranges_overlap(100, 200, 100, 200));
        $this->assertFalse($this->stub->ranges_overlap(100, 200, 200, 300)); // adjacent, not overlapping
        $this->assertFalse($this->stub->ranges_overlap(100, 200, 50, 100));
        $this->assertFalse($this->stub->ranges_overlap(100, 200, 300, 400));
    }

    public function testUnionExpansionCoversLongerOverlappingEvent(): void
    {
        // Existing 08:00-08:30 + incoming 08:00-09:00 must become 08:00-09:00.
        $existing_start = strtotime('2026-11-18 08:00:00');
        $existing_end = strtotime('2026-11-18 08:30:00');
        $incoming_start = strtotime('2026-11-18 08:00:00');
        $incoming_end = strtotime('2026-11-18 09:00:00');

        $this->assertTrue(
            $this->stub->ranges_overlap($incoming_start, $incoming_end, $existing_start, $existing_end),
        );

        $union_start = min($incoming_start, $existing_start);
        $union_end = max($incoming_end, $existing_end);

        $this->assertSame($incoming_start, $union_start);
        $this->assertSame($incoming_end, $union_end);
    }

    public function testSourceDefinesSyntheticSkipAndCollapse(): void
    {
        $root = dirname(__DIR__, 2);
        $google = file_get_contents($root . '/application/controllers/Google.php');
        $lib = file_get_contents($root . '/application/libraries/Google_sync.php');
        $reset = file_get_contents($root . '/application/controllers/Google_calendar_sync_status.php');

        $this->assertStringContainsString('is_synthetic_unavailable_event', $lib);
        $this->assertStringContainsString('remove_unavailable_events', $lib);
        $this->assertStringContainsString('is_synthetic_unavailable_event', $google);
        $this->assertStringContainsString('expanded_overlap', $google);
        $this->assertStringContainsString('collapse_overlapping_google_unavailabilities', $google);
        $this->assertStringContainsString('remove_unavailable_events', $reset);
    }
}
