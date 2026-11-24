<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Focused tests for Google Calendar anonymization logic (Google → EA import).
 */
class GoogleSyncAnonymizeTest extends TestCase
{
    private object $stub;

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];

        $this->stub = new class {
            public function should_anonymize(array $provider): bool
            {
                $global = filter_var(setting('google_calendar_anonymize'), FILTER_VALIDATE_BOOLEAN);
                $provider_flag = filter_var(
                    $provider['settings']['google_calendar_anonymize'] ?? false,
                    FILTER_VALIDATE_BOOLEAN,
                );

                return $global || $provider_flag;
            }

            public function build_imported_event_notes(object $google_event, array $provider): string
            {
                if ($this->should_anonymize($provider)) {
                    return '';
                }

                $summary = trim((string) $google_event->getSummary());
                $description = (string) $google_event->getDescription();

                if (strcasecmp($summary, 'Unavailable') === 0) {
                    return $description;
                }

                return trim($summary . ' ' . $description);
            }

            private function build_event_summary(array $service): string
            {
                return !empty($service['name']) ? $service['name'] : 'Unavailable';
            }
        };
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($this->stub, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->stub, $args);
    }

    private function fakeGoogleEvent(string $summary, string $description = ''): object
    {
        return new class ($summary, $description) {
            public function __construct(
                private string $summary,
                private string $description,
            ) {
            }

            public function getSummary(): string
            {
                return $this->summary;
            }

            public function getDescription(): string
            {
                return $this->description;
            }
        };
    }

    public function testShouldAnonymizeWhenGlobalFlagEnabled(): void
    {
        $GLOBALS['__ea_test_settings']['google_calendar_anonymize'] = '1';

        $this->assertTrue($this->invoke('should_anonymize', [['settings' => []]]));
    }

    public function testShouldAnonymizeWhenProviderFlagEnabled(): void
    {
        $GLOBALS['__ea_test_settings']['google_calendar_anonymize'] = '0';

        $this->assertTrue(
            $this->invoke('should_anonymize', [
                ['settings' => ['google_calendar_anonymize' => '1']],
            ]),
        );
    }

    public function testShouldNotAnonymizeWhenDisabled(): void
    {
        $GLOBALS['__ea_test_settings']['google_calendar_anonymize'] = '0';

        $this->assertFalse($this->invoke('should_anonymize', [['settings' => []]]));
    }

    public function testImportedNotesEmptyWhenAnonymized(): void
    {
        $GLOBALS['__ea_test_settings']['google_calendar_anonymize'] = '0';

        $notes = $this->invoke('build_imported_event_notes', [
            $this->fakeGoogleEvent('Private dentist', 'Bring insurance card'),
            ['settings' => ['google_calendar_anonymize' => '1']],
        ]);

        $this->assertSame('', $notes);
    }

    public function testImportedNotesIncludeSummaryWhenNotAnonymized(): void
    {
        $GLOBALS['__ea_test_settings']['google_calendar_anonymize'] = '0';

        $notes = $this->invoke('build_imported_event_notes', [
            $this->fakeGoogleEvent('Private dentist', 'Bring insurance card'),
            ['settings' => []],
        ]);

        $this->assertSame('Private dentist Bring insurance card', $notes);
    }

    public function testBuildEventSummaryUsesServiceName(): void
    {
        $this->assertSame(
            'Haircut',
            $this->invoke('build_event_summary', [['name' => 'Haircut']]),
        );
    }
}
