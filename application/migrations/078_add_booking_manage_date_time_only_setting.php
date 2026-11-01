<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_booking_manage_date_time_only_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'booking_manage_date_time_only'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'booking_manage_date_time_only',
                'value' => '0',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'booking_manage_date_time_only']);
    }
}
