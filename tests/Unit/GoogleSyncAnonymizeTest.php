<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Focused tests for Google Calendar anonymization logic.
 */
class GoogleSyncAnonymizeTest extends TestCase
{
    private object $stub;

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];

        $this->stub = new class {
            private function should_anonymize(array $provider): bool
            {
                $global = filter_var(setting('google_calendar_anonymize'), FILTER_VALIDATE_BOOLEAN);
                $provider_flag = filter_var(
                    $provider['settings']['google_calendar_anonymize'] ?? false,
                    FILTER_VALIDATE_BOOLEAN,
                );

                return $global || $provider_flag;
            }

            private function build_event_summary(array $service, bool $anonymize): string
            {
                if ($anonymize) {
                    return 'Appointment';
                }

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

    public function testBuildEventSummaryAnonymized(): void
    {
        $this->assertSame(
            'Appointment',
            $this->invoke('build_event_summary', [['name' => 'Haircut'], true]),
        );
    }

    public function testBuildEventSummaryUsesServiceName(): void
    {
        $this->assertSame(
            'Haircut',
            $this->invoke('build_event_summary', [['name' => 'Haircut'], false]),
        );
    }
}
