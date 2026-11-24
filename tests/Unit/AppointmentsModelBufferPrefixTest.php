<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Buffer conflict SQL must honor CI dbprefix (ea_appointments), otherwise
 * status updates fail with: Unknown column 'appointments.end_datetime'.
 */
class AppointmentsModelBufferPrefixTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testConflictSqlUsesPrefixedEndExpressionHelper(): void
    {
        $source = file_get_contents($this->root . '/application/models/Appointments_model.php');

        $this->assertStringContainsString('function appointment_end_sql_expression', $source);
        $this->assertStringContainsString('function has_provider_conflict', $source);
        $this->assertStringContainsString('$this->appointment_end_sql_expression($use_buffer)', $source);
        $this->assertStringContainsString("dbprefix('appointments')", $source);
        $this->assertStringContainsString("dbprefix('services')", $source);
        $this->assertStringContainsString('Unknown column \'appointments.end_datetime\'', $source);

        $this->assertStringNotContainsString(
            "DATE_ADD(appointments.end_datetime, INTERVAL IFNULL(services.buffer_after, 0) MINUTE)",
            $source,
        );
        $this->assertDoesNotMatchRegularExpression(
            "/\\\$existing_end_expression\s*=\s*'appointments\.end_datetime'/",
            $source,
        );
    }
}
