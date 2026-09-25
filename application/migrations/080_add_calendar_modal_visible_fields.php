<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Calendar appointment modal field visibility settings.
 */
class Migration_Add_calendar_modal_visible_fields extends EA_Migration
{
    public function up(): void
    {
        $defaults = json_encode([
            'service' => true,
            'provider' => true,
            'color' => true,
            'location' => true,
            'meeting_link' => true,
            'status' => true,
            'start_datetime' => true,
            'end_datetime' => true,
            'timezone' => true,
            'notes' => true,
            'first_name' => true,
            'last_name' => true,
            'email' => true,
            'phone_number' => true,
            'language' => true,
            'address' => true,
            'city' => true,
            'zip_code' => true,
            'customer_timezone' => true,
            'customer_notes' => true,
            'custom_fields' => true,
            'utm' => false,
        ]);

        if (!$this->db->get_where('settings', ['name' => 'calendar_modal_visible_fields'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_modal_visible_fields',
                'value' => $defaults,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'calendar_modal_visible_fields']);
    }
}
