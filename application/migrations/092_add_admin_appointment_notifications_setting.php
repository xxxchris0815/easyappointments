<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Global toggle: notify admins about new/changed/cancelled appointments.
 */
class Migration_Add_admin_appointment_notifications_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'admin_appointment_notifications'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'admin_appointment_notifications',
                'value' => '1',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'admin_appointment_notifications']);
    }
}
