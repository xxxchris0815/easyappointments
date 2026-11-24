<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Add Mautic API credentials settings.
 * ---------------------------------------------------------------------------- */

class Migration_Add_mautic_api_settings extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $settings = [
            ['name' => 'mautic_lookup_mode', 'value' => 'api'],
            ['name' => 'mautic_api_url', 'value' => ''],
            ['name' => 'mautic_api_username', 'value' => ''],
            ['name' => 'mautic_api_password', 'value' => ''],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        foreach (['mautic_lookup_mode', 'mautic_api_url', 'mautic_api_username', 'mautic_api_password'] as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }
    }
}
