<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin UI for booking blacklist (email / phone / Mautic id).
 */
class Booking_blacklist_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');
        $this->load->model('settings_model');
        $this->load->library('booking_blacklist');
        $this->load->library('accounts');

        if (can('edit', PRIV_SYSTEM_SETTINGS) === false && can('view', PRIV_SYSTEM_SETTINGS) === false) {
            show_error('Forbidden', 403);
        }
    }

    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('booking_blacklist_settings')]);

        $user_id = session('user_id');

        if (!$user_id) {
            redirect('login');
            return;
        }

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            abort(403, 'Forbidden');
        }

        $entries = $this->booking_blacklist->get_entries();

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
            'booking_blacklist' => $entries,
        ]);

        html_vars([
            'page_title' => lang('booking_blacklist'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
        ]);

        $this->load->view('pages/booking_blacklist_settings');
    }

    public function save(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $entries = request('booking_blacklist', []);

            if (is_string($entries)) {
                $decoded = json_decode($entries, true);
                $entries = is_array($decoded) ? $decoded : [];
            }

            $clean = $this->booking_blacklist->sanitize_entries($entries);

            setting(['booking_blacklist' => json_encode($clean, JSON_UNESCAPED_UNICODE)]);

            json_response([
                'success' => true,
                'booking_blacklist' => $clean,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Recent blocked booking attempts (JSON).
     */
    public function logs(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $limit = max(1, min(200, (int) request('limit', 50)));
            $path = $this->log_file_path();
            $entries = [];

            if (is_file($path)) {
                $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $slice = array_slice($lines, -$limit);

                foreach ($slice as $line) {
                    $decoded = json_decode($line, true);

                    if (is_array($decoded)) {
                        $entries[] = $decoded;
                    }
                }
            }

            json_response([
                'success' => true,
                'count' => count($entries),
                'entries' => $entries,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    private function log_file_path(): string
    {
        $dir = config('log_path') ?: (FCPATH . 'storage/logs/');

        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'booking_blacklist.log';
    }
}
