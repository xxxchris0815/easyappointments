<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Global Google/CalDAV sync window (past/future days).
 *
 * Provider user_settings still store per-provider values; saving the global
 * settings applies them to all providers so Sync Status stays consistent.
 */
class Migration_Add_google_sync_window_settings extends EA_Migration
{
    public function up(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'google_sync_past_days'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'google_sync_past_days',
                'value' => '30',
            ]);
        }

if (!$this->db->get_where('settings', ['name' => 'google_sync_future_days'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'google_sync_future_days',
                'value' => '90',
            ]);
        }

        // Align existing provider windows with the new global defaults when they
        // are still on the historic short horizon (5 days from older installs).
        if ($this->db->table_exists('user_settings')) {
            $this->db
                ->where('sync_future_days IS NOT NULL', null, false)
                ->where('sync_future_days <=', 5)
                ->update('user_settings', [
                    'sync_past_days' => 30,
                    'sync_future_days' => 90,
                ]);
        }
    }

    public function down(): void
    {
        $this->db->delete('settings', ['name' => 'google_sync_past_days']);
        $this->db->delete('settings', ['name' => 'google_sync_future_days']);
    }
}
