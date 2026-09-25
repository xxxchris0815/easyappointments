<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Soft-cancel appointments: keep Cancelled rows for analytics.
 * ---------------------------------------------------------------------------- */

class Migration_Add_soft_cancel_appointments extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $setting = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!$setting) {
            $this->db->insert('settings', [
                'name' => 'appointment_status_options',
                'value' => '["Booked","Confirmed","Rescheduled","Cancelled","Draft"]',
            ]);

            return;
        }

        $options = json_decode((string) $setting['value'], true);

        if (!is_array($options)) {
            $options = [];
        }

        $normalized = array_map(static fn($option) => strtolower(trim((string) $option)), $options);

        if (!in_array('cancelled', $normalized, true) && !in_array('canceled', $normalized, true)) {
            $options[] = APPOINTMENT_STATUS_CANCELLED;
            $this->db->update(
                'settings',
                ['value' => json_encode(array_values($options))],
                ['name' => 'appointment_status_options'],
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // Keep Cancelled in status options; no destructive rollback.
    }
}
