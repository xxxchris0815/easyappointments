<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Allow providers to see aggregated free/busy across all providers of a service
 * in the calendar service filter view.
 */
class Migration_Add_provider_service_calendar_free_busy_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'provider_service_calendar_free_busy'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'provider_service_calendar_free_busy',
                'value' => '0',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'provider_service_calendar_free_busy']);
    }
}
