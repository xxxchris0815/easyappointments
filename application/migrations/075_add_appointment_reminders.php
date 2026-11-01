<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Appointment reminder settings + delivery ledger.
 * ---------------------------------------------------------------------------- */

class Migration_Add_appointment_reminders extends EA_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        $settings = [
            ['name' => 'appointment_reminders_enabled', 'value' => '0'],
            [
                'name' => 'appointment_reminders',
                'value' => json_encode([
                    [
                        'id' => 'r1',
                        'offset' => 24,
                        'unit' => 'hours',
                        'channels' => ['email'],
                    ],
                ]),
            ],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }

        if (!$this->db->table_exists('appointment_reminder_deliveries')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'create_datetime' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'update_datetime' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'id_appointments' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'reminder_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
                'channel' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'null' => false,
                ],
                'due_datetime' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'sent_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 32,
                    'null' => false,
                    'default' => 'pending',
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('id_appointments');
            $this->dbforge->add_key(['status', 'due_datetime']);
            $this->dbforge->create_table('appointment_reminder_deliveries', true);

            $this->db->query(
                'ALTER TABLE `' .
                    $this->db->dbprefix('appointment_reminder_deliveries') .
                    '` ADD UNIQUE KEY `appointment_reminder_unique` (`id_appointments`, `reminder_key`, `channel`)',
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('appointment_reminder_deliveries')) {
            $this->dbforge->drop_table('appointment_reminder_deliveries', true);
        }

        foreach (['appointment_reminders_enabled', 'appointment_reminders'] as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }
    }
}
