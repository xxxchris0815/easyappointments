<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Whether calendar appointment modal allows changing the provider select.
 */
class Migration_Add_calendar_provider_select_editable_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'calendar_provider_select_editable'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_provider_select_editable',
                'value' => '1',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'calendar_provider_select_editable']);
    }
}
