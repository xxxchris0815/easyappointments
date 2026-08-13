<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Smtp_settings controller.
 *
 * Backend SMTP configuration for confirmation and reminder emails.
 */
class Smtp_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');

        if (can('edit', PRIV_SYSTEM_SETTINGS) === false && can('view', PRIV_SYSTEM_SETTINGS) === false) {
            show_error('Forbidden', 403);
        }
    }

    public function index(): void
    {
        $user_id = session('user_id');

        $smtp_settings = [
            ['name' => 'smtp_enabled', 'value' => setting('smtp_enabled', '0')],
            ['name' => 'smtp_host', 'value' => setting('smtp_host', '')],
            ['name' => 'smtp_port', 'value' => setting('smtp_port', '587')],
            ['name' => 'smtp_crypto', 'value' => setting('smtp_crypto', 'tls')],
            ['name' => 'smtp_user', 'value' => setting('smtp_user', '')],
            ['name' => 'smtp_pass', 'value' => setting('smtp_pass', '')],
            ['name' => 'smtp_from_name', 'value' => setting('smtp_from_name', '')],
            ['name' => 'smtp_from_address', 'value' => setting('smtp_from_address', '')],
            ['name' => 'smtp_reply_to', 'value' => setting('smtp_reply_to', '')],
        ];

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'smtp_settings' => $smtp_settings,
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/smtp_settings');
    }

    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('smtp_settings', 'array|null');

            foreach (request('smtp_settings', []) as $smtp_setting) {
                $name = (string) ($smtp_setting['name'] ?? '');
                $value = (string) ($smtp_setting['value'] ?? '');

                $allowed = [
                    'smtp_enabled',
                    'smtp_host',
                    'smtp_port',
                    'smtp_crypto',
                    'smtp_user',
                    'smtp_pass',
                    'smtp_from_name',
                    'smtp_from_address',
                    'smtp_reply_to',
                ];

                if (!in_array($name, $allowed, true)) {
                    continue;
                }

                // Keep existing password when the field is submitted empty.
                if ($name === 'smtp_pass' && $value === '') {
                    continue;
                }

                setting([$name => $value]);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
