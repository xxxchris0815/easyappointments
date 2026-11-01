<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Google ↔ EA origin metadata helpers.
 */
class GoogleSyncOriginMetadataTest extends TestCase
{
    private object $stub;

    protected function setUp(): void
    {
        $this->stub = new class {
            public const EA_EXTENDED_APPOINTMENT_ID = 'ea_appointment_id';
            public const EA_EXTENDED_ORIGIN = 'ea_origin';
            public const EA_ORIGIN_VALUE = 'easyappointments';

            public function apply_ea_origin_metadata($event, int $appointment_id): void
            {
                if ($appointment_id <= 0 || !is_object($event) || !method_exists($event, 'setExtendedProperties')) {
                    return;
                }

                $private = [
                    self::EA_EXTENDED_APPOINTMENT_ID => (string) $appointment_id,
                    self::EA_EXTENDED_ORIGIN => self::EA_ORIGIN_VALUE,
                ];

                $existing = method_exists($event, 'getExtendedProperties') ? $event->getExtendedProperties() : null;

                if ($existing && method_exists($existing, 'getPrivate')) {
                    $existing_private = $existing->getPrivate() ?: [];
                    if (is_array($existing_private)) {
                        $private = array_merge($existing_private, $private);
                    }
                }

                $event->setExtendedProperties(
                    new class ($private) {
                        public function __construct(private array $private)
                        {
                        }

                        public function getPrivate(): array
                        {
                            return $this->private;
                        }
                    },
                );
            }

            public function get_ea_appointment_id_from_event($event): ?int
            {
                if (!is_object($event) || !method_exists($event, 'getExtendedProperties')) {
                    return null;
                }

                $extended = $event->getExtendedProperties();

                if (!$extended || !method_exists($extended, 'getPrivate')) {
                    return null;
                }

                $private = $extended->getPrivate();

                if (!is_array($private)) {
                    return null;
                }

                $raw = $private[self::EA_EXTENDED_APPOINTMENT_ID] ?? null;

                if ($raw === null || $raw === '') {
                    return null;
                }

                $id = filter_var($raw, FILTER_VALIDATE_INT);

                return $id !== false && $id > 0 ? (int) $id : null;
            }

            public function is_ea_origin_event($event): bool
            {
                if ($this->get_ea_appointment_id_from_event($event) !== null) {
                    return true;
                }

                if (!is_object($event) || !method_exists($event, 'getExtendedProperties')) {
                    return false;
                }

                $extended = $event->getExtendedProperties();

                if (!$extended || !method_exists($extended, 'getPrivate')) {
                    return false;
                }

                $private = $extended->getPrivate();

                if (!is_array($private)) {
                    return false;
                }

                return ($private[self::EA_EXTENDED_ORIGIN] ?? null) === self::EA_ORIGIN_VALUE;
            }
        };
    }

    private function fakeEvent(?array $private = null): object
    {
        return new class ($private) {
            private mixed $extended;

            public function __construct(?array $private)
            {
                $this->extended =
                    $private === null
                        ? null
                        : new class ($private) {
                            public function __construct(private array $private)
                            {
                            }

                            public function getPrivate(): array
                            {
                                return $this->private;
                            }
                        };
            }

            public function getExtendedProperties(): mixed
            {
                return $this->extended;
            }

            public function setExtendedProperties(mixed $extended): void
            {
                $this->extended = $extended;
            }
        };
    }

    public function testApplyOriginMetadataStoresAppointmentId(): void
    {
        $event = $this->fakeEvent();
        $this->stub->apply_ea_origin_metadata($event, 42);

        $this->assertSame(42, $this->stub->get_ea_appointment_id_from_event($event));
        $this->assertTrue($this->stub->is_ea_origin_event($event));
    }

    public function testApplyOriginMetadataMergesExistingPrivateProps(): void
    {
        $event = $this->fakeEvent(['custom' => 'keep-me']);
        $this->stub->apply_ea_origin_metadata($event, 7);

        $private = $event->getExtendedProperties()->getPrivate();

        $this->assertSame('keep-me', $private['custom']);
        $this->assertSame('7', $private['ea_appointment_id']);
        $this->assertSame('easyappointments', $private['ea_origin']);
    }

    public function testForeignGoogleEventIsNotEaOrigin(): void
    {
        $event = $this->fakeEvent(['something' => 'else']);

        $this->assertNull($this->stub->get_ea_appointment_id_from_event($event));
        $this->assertFalse($this->stub->is_ea_origin_event($event));
    }

    public function testInvalidAppointmentIdIsIgnored(): void
    {
        $event = $this->fakeEvent();
        $this->stub->apply_ea_origin_metadata($event, 0);

        $this->assertNull($event->getExtendedProperties());
        $this->assertFalse($this->stub->is_ea_origin_event($event));
    }
}
