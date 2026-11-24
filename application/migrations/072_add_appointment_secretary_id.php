<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * id_users_secretary on appointments was briefly added but is redundant with
 * id_users_created_by. Keep this migration as a no-op add guard for installs
 * that never received the column, and drop it if present.
 * ---------------------------------------------------------------------------- */

class Migration_Add_appointment_secretary_id extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if ($this->db->field_exists('id_users_secretary', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_users_secretary');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        // Intentionally left empty: the appointment secretary column is not restored.
    }
}
