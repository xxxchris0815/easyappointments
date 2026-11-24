<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Configurable Zoom meeting topic template with appointment placeholders.
 */
class Migration_Add_zoom_meeting_topic_template extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'zoom_meeting_topic_template'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'zoom_meeting_topic_template',
                'value' => '{service} - {customer_first_name} - {customer_email}',
            ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'zoom_meeting_topic_template']);
    }
}
