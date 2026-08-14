<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * UTM tracking columns + timeslot display settings.
 */
class Migration_Add_utm_and_timeslot_settings extends EA_Migration
{
    public function up(): void
    {
        $utm_fields = [
            'utm_source' => [
                'type' => 'VARCHAR',
                'constraint' => '191',
                'null' => true,
                'default' => null,
            ],
            'utm_medium' => [
                'type' => 'VARCHAR',
                'constraint' => '191',
                'null' => true,
                'default' => null,
            ],
            'utm_campaign' => [
                'type' => 'VARCHAR',
                'constraint' => '191',
                'null' => true,
                'default' => null,
            ],
            'utm_term' => [
                'type' => 'VARCHAR',
                'constraint' => '191',
                'null' => true,
                'default' => null,
            ],
            'utm_content' => [
                'type' => 'VARCHAR',
                'constraint' => '191',
                'null' => true,
                'default' => null,
            ],
        ];

        foreach ($utm_fields as $name => $definition) {
            if (!$this->db->field_exists($name, 'appointments')) {
                $this->dbforge->add_column('appointments', [$name => $definition]);
            }
        }

        $settings = [
            ['name' => 'booking_utm_tracking_enabled', 'value' => '0'],
            ['name' => 'booking_timeslot_columns', 'value' => '1'],
            ['name' => 'booking_timeslot_page_size', 'value' => '0'],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }
    }

    public function down(): void
    {
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $name) {
            if ($this->db->field_exists($name, 'appointments')) {
                $this->dbforge->drop_column('appointments', $name);
            }
        }

        $this->db->where_in('name', [
            'booking_utm_tracking_enabled',
            'booking_timeslot_columns',
            'booking_timeslot_page_size',
        ])->delete('settings');
    }
}
