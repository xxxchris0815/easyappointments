<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Redirect to an external success URL after booking, with appointment placeholders.
 */
class Migration_Add_booking_success_redirect_url extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'booking_success_redirect_url'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'booking_success_redirect_url',
                'value' => '',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'booking_success_redirect_url']);
    }
}
