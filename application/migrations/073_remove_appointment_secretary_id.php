<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Remove redundant appointment id_users_secretary column for databases that
 * already applied the earlier version of migration 072.
 * ---------------------------------------------------------------------------- */

class Migration_Remove_appointment_secretary_id extends EA_Migration
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
