<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking blacklist entries (email / phone / Mautic id).
 */
class Migration_Add_booking_blacklist_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'booking_blacklist'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'booking_blacklist',
                'value' => '[]',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'booking_blacklist']);
    }
}
