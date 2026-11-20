<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks: providers must see Enable Sync after calendar filter auto-select.
 */
class CalendarProviderSyncButtonSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCalendarSyncInitializesAfterDefaultViewFilterIsReady(): void
    {
        $calendarPage = file_get_contents($this->root . '/assets/js/pages/calendar.js');
        $calendarSync = file_get_contents($this->root . '/assets/js/utils/calendar_sync.js');
        $defaultView = file_get_contents($this->root . '/assets/js/utils/calendar_default_view.js');

        // Sync must not race ahead via its own DOMContentLoaded before the filter exists.
        $this->assertStringNotContainsString(
            "document.addEventListener('DOMContentLoaded', initialize)",
            $calendarSync,
        );

        // calendar.js boots DefaultView first, then CalendarSync.
        $defaultPos = strpos($calendarPage, 'App.Utils.CalendarDefaultView.initialize()');
        $syncPos = strpos($calendarPage, 'App.Utils.CalendarSync.initialize()');
        $this->assertNotFalse($defaultPos);
        $this->assertNotFalse($syncPos);
        $this->assertGreaterThan($defaultPos, $syncPos);

        // Auto-selected provider filter must emit change (updates Enable Sync visibility).
        $this->assertStringContainsString('$selectFilterItem.trigger(\'change\')', $defaultView);
        $this->assertStringContainsString('Enable Sync', $defaultView);
    }
}
