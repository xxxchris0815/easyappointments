<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Custom fork features migration.
 * ---------------------------------------------------------------------------- */

class Migration_Add_custom_fork_features extends EA_Migration
{
    private const MAX_CUSTOM_FIELDS = 20;

    private const DEFAULT_CUSTOM_FIELDS = 5;

    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $settings = [
            ['name' => 'google_calendar_anonymize', 'value' => '0'],
            ['name' => 'booking_tracking_enabled', 'value' => '0'],
            ['name' => 'booking_tracking_webhook_url', 'value' => ''],
            ['name' => 'secretary_restricted_view', 'value' => '0'],
            ['name' => 'zoom_enabled', 'value' => '0'],
            ['name' => 'zoom_account_id', 'value' => ''],
            ['name' => 'zoom_client_id', 'value' => ''],
            ['name' => 'zoom_client_secret', 'value' => ''],
            ['name' => 'zoom_store_join_url_in_location', 'value' => '0'],
            ['name' => 'custom_fields_count', 'value' => (string) self::DEFAULT_CUSTOM_FIELDS],
            ['name' => 'custom_head_scripts', 'value' => ''],
            ['name' => 'mautic_lead_lookup_enabled', 'value' => '1'],
            ['name' => 'mautic_lead_lookup_url', 'value' => 'https://automation.orgasmic.live/webhook/mautic-lead-lookup'],
            ['name' => 'hide_booking_timezone_selector', 'value' => '1'],
            ['name' => 'hide_booking_custom_fields', 'value' => '1'],
            ['name' => 'hide_booking_single_provider', 'value' => '1'],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }

        // Extend custom fields beyond the default five.
        for ($i = self::DEFAULT_CUSTOM_FIELDS + 1; $i <= self::MAX_CUSTOM_FIELDS; $i++) {
            $field_name = 'custom_field_' . $i;

            if (!$this->db->field_exists($field_name, 'users')) {
                $this->dbforge->add_column('users', [
                    $field_name => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                ]);
            }

            foreach (['display' => '0', 'require' => '0', 'label' => ''] as $prefix => $default_value) {
                $setting_name = $prefix . '_' . $field_name;

                if (!$this->db->get_where('settings', ['name' => $setting_name])->num_rows()) {
                    $this->db->insert('settings', [
                        'name' => $setting_name,
                        'value' => $default_value,
                    ]);
                }
            }
        }

        if (!$this->db->field_exists('id_users_created_by', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'id_users_created_by' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'null' => true,
                    'after' => 'id_users_customer',
                ],
            ]);
        }

        if (!$this->db->field_exists('id_zoom_meeting', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'id_zoom_meeting' => [
                    'type' => 'VARCHAR',
                    'constraint' => '128',
                    'null' => true,
                    'after' => 'meeting_link',
                ],
            ]);
        }

        if (!$this->db->field_exists('google_calendar_anonymize', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'google_calendar_anonymize' => [
                    'type' => 'TINYINT',
                    'constraint' => '4',
                    'default' => 0,
                    'after' => 'google_calendar',
                ],
            ]);
        }

        if (!$this->db->field_exists('zoom_email', 'user_settings')) {
            $this->dbforge->add_column('user_settings', [
                'zoom_email' => [
                    'type' => 'VARCHAR',
                    'constraint' => '512',
                    'null' => true,
                ],
            ]);
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        $setting_names = [
            'google_calendar_anonymize',
            'booking_tracking_enabled',
            'booking_tracking_webhook_url',
            'secretary_restricted_view',
            'zoom_enabled',
            'zoom_account_id',
            'zoom_client_id',
            'zoom_client_secret',
            'zoom_store_join_url_in_location',
            'custom_fields_count',
            'custom_head_scripts',
            'mautic_lead_lookup_enabled',
            'mautic_lead_lookup_url',
            'hide_booking_timezone_selector',
            'hide_booking_custom_fields',
            'hide_booking_single_provider',
        ];

        foreach ($setting_names as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }

        for ($i = self::DEFAULT_CUSTOM_FIELDS + 1; $i <= self::MAX_CUSTOM_FIELDS; $i++) {
            $field_name = 'custom_field_' . $i;

            if ($this->db->field_exists($field_name, 'users')) {
                $this->dbforge->drop_column('users', $field_name);
            }

            foreach (['display', 'require', 'label'] as $prefix) {
                $this->db->delete('settings', ['name' => $prefix . '_' . $field_name]);
            }
        }

        if ($this->db->field_exists('id_users_created_by', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_users_created_by');
        }

        if ($this->db->field_exists('id_zoom_meeting', 'appointments')) {
            $this->dbforge->drop_column('appointments', 'id_zoom_meeting');
        }

        if ($this->db->field_exists('google_calendar_anonymize', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'google_calendar_anonymize');
        }

        if ($this->db->field_exists('zoom_email', 'user_settings')) {
            $this->dbforge->drop_column('user_settings', 'zoom_email');
        }
    }
}
