<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Scheduled calendar sync (Google / CalDAV) controlled from app settings.
 * ---------------------------------------------------------------------------- */

class Migration_Add_calendar_sync_cron_settings extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'calendar_sync_cron_enabled'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_sync_cron_enabled',
                'value' => '0',
            ]);
        }

        if (!$this->db->get_where('settings', ['name' => 'calendar_sync_cron_interval_minutes'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_sync_cron_interval_minutes',
                'value' => '60',
            ]);
        }

        if (!$this->db->get_where('settings', ['name' => 'calendar_sync_cron_last_run'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'calendar_sync_cron_last_run',
                'value' => '0',
            ]);
        }
    }

    public function down(): void
    {
        foreach (
            ['calendar_sync_cron_enabled', 'calendar_sync_cron_interval_minutes', 'calendar_sync_cron_last_run']
            as $name
        ) {
            $this->db->delete('settings', ['name' => $name]);
        }
    }
}
