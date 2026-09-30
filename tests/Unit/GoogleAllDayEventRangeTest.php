<?php

namespace Tests\Unit;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * All-day Google event range extraction (birthday / holiday day-flip regression).
 */
class GoogleAllDayEventRangeTest extends TestCase
{
    private object $stub;

    protected function setUp(): void
    {
        $this->stub = new class {
            public function extract_event_range($google_event, DateTimeZone $provider_timezone): ?array
            {
                if (
                    !is_object($google_event) ||
                    !method_exists($google_event, 'getStart') ||
                    !method_exists($google_event, 'getEnd') ||
                    $google_event->getStart() === null ||
                    $google_event->getEnd() === null
                ) {
                    return null;
                }

                $start = $google_event->getStart();
                $end = $google_event->getEnd();

                $start_date = method_exists($start, 'getDate') ? $start->getDate() : null;
                $end_date = method_exists($end, 'getDate') ? $end->getDate() : null;
                $is_all_day = !empty($start_date) && !empty($end_date);

                if ($is_all_day) {
                    $g_start = new DateTime($start_date . ' 00:00:00', $provider_timezone);
                    $g_end = new DateTime($end_date . ' 00:00:00', $provider_timezone);
                    $g_end->modify('-1 second');

                    if ($g_end <= $g_start) {
                        return null;
                    }
                } else {
                    $start_dt = method_exists($start, 'getDateTime') ? $start->getDateTime() : null;
                    $end_dt = method_exists($end, 'getDateTime') ? $end->getDateTime() : null;

                    if (empty($start_dt) || empty($end_dt) || $start_dt === $end_dt) {
                        return null;
                    }

                    $g_start = new DateTime($start_dt);
                    $g_start->setTimezone($provider_timezone);
                    $g_end = new DateTime($end_dt);
                    $g_end->setTimezone($provider_timezone);
                }

                return [$g_start->getTimestamp(), $g_end->getTimestamp()];
            }
        };
    }

    private function eventWithDateRange(?string $start_date, ?string $end_date, ?string $start_dt = null, ?string $end_dt = null): object
    {
        return new class ($start_date, $end_date, $start_dt, $end_dt) {
            public function __construct(
                private ?string $start_date,
                private ?string $end_date,
                private ?string $start_dt,
                private ?string $end_dt,
            ) {
            }

            public function getStart(): object
            {
                return new class ($this->start_date, $this->start_dt) {
                    public function __construct(
                        private ?string $date,
                        private ?string $date_time,
                    ) {
                    }

                    public function getDate(): ?string
                    {
                        return $this->date;
                    }

                    public function getDateTime(): ?string
                    {
                        return $this->date_time;
                    }
                };
            }

            public function getEnd(): object
            {
                return new class ($this->end_date, $this->end_dt) {
                    public function __construct(
                        private ?string $date,
                        private ?string $date_time,
                    ) {
                    }

                    public function getDate(): ?string
                    {
                        return $this->date;
                    }

                    public function getDateTime(): ?string
                    {
                        return $this->date_time;
                    }
                };
            }
        };
    }

    public function testOneDayAllDayBirthdayStaysOnOctober3InBerlin(): void
    {
        $tz = new DateTimeZone('Europe/Berlin');
        // Google exclusive end: birthday on 3 Oct → end.date = 4 Oct
        $range = $this->stub->extract_event_range(
            $this->eventWithDateRange('2026-10-03', '2026-10-04'),
            $tz,
        );

        $this->assertNotNull($range);
        [$start_ts, $end_ts] = $range;

        $start = (new DateTime('@' . $start_ts))->setTimezone($tz);
        $end = (new DateTime('@' . $end_ts))->setTimezone($tz);

        $this->assertSame('2026-10-03 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-03 23:59:59', $end->format('Y-m-d H:i:s'));
        $this->assertSame('Saturday', $start->format('l'));
        $this->assertSame('Saturday', $end->format('l'));
    }

    public function testPrefersDateFieldsOverDateTimeToAvoidDayFlip(): void
    {
        $tz = new DateTimeZone('Europe/Berlin');
        // Recurring expansions sometimes also set dateTime in UTC midnight —
        // that must not win over the floating date fields.
        $range = $this->stub->extract_event_range(
            $this->eventWithDateRange(
                '2026-10-03',
                '2026-10-04',
                '2026-10-02T22:00:00Z',
                '2026-10-03T22:00:00Z',
            ),
            $tz,
        );

        $this->assertNotNull($range);
        $start = (new DateTime('@' . $range[0]))->setTimezone($tz);
        $this->assertSame('2026-10-03', $start->format('Y-m-d'));
    }

    public function testTimedEventUsesDateTimeTimezoneConversion(): void
    {
        $tz = new DateTimeZone('Europe/Berlin');
        $range = $this->stub->extract_event_range(
            $this->eventWithDateRange(null, null, '2026-10-03T08:00:00Z', '2026-10-03T09:00:00Z'),
            $tz,
        );

        $this->assertNotNull($range);
        $start = (new DateTime('@' . $range[0]))->setTimezone($tz);
        $end = (new DateTime('@' . $range[1]))->setTimezone($tz);

        $this->assertSame('2026-10-03 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-03 11:00:00', $end->format('Y-m-d H:i:s'));
    }
}
