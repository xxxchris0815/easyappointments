<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Any-provider assignment mode + per-provider weights.
 */
class Migration_Add_any_provider_assignment_settings extends EA_Migration
{
    public function up(): void
    {
        $settings = [
            ['name' => 'any_provider_selection_mode', 'value' => 'most_available'],
            ['name' => 'any_provider_rr_counter', 'value' => '0'],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }

        if (!$this->db->field_exists('any_provider_weight', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'any_provider_weight' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 1,
                    'null' => false,
                    'after' => 'calendar_view',
                ],
            ]);
        }
    }

    public function down(): void
    {
        foreach (['any_provider_selection_mode', 'any_provider_rr_counter'] as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }

        if ($this->db->field_exists('any_provider_weight', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'any_provider_weight');
        }
    }
}
