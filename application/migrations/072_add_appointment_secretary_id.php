<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Add secretary booking ownership column on appointments.
 * ---------------------------------------------------------------------------- */

class Migration_Add_appointment_secretary_id extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->field_exists('id_users_secretary', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'id_users_secretary' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'null' => true,
                    'after' => 'id_users_created_by',
                ],
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('id_users_secretary', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_users_secretary');
        }
    }
}
