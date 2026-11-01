<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_hide_booking_header_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'hide_booking_header'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'hide_booking_header',
                'value' => '0',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'hide_booking_header']);
    }
}
