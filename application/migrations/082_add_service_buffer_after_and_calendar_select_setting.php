<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Per-service buffer after appointments + calendar select opens appointment setting.
 */
class Migration_Add_service_buffer_after_and_calendar_select_setting extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->field_exists('buffer_after', 'services')) {
            $this->dbforge->add_column('services', [
                'buffer_after' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'slot_interval',
                ],
            ]);
        }

        if (!$this->db->get_where('settings', ['name' => 'calendar_select_opens_appointment'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_select_opens_appointment',
                'value' => '1',
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->field_exists('buffer_after', 'services')) {
            $this->dbforge->drop_column('services', 'buffer_after');
        }

        $this->db->delete('settings', ['name' => 'calendar_select_opens_appointment']);
    }
}
