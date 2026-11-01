<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Handles Mautic integration settings.
 * ---------------------------------------------------------------------------- */

/**
 * Mautic_settings controller.
 *
 * @package Controllers
 */
class Mautic_settings extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');

        if (can('edit', PRIV_SYSTEM_SETTINGS) === false) {
            show_error('Forbidden', 403);
        }
    }

    public function index(): void
    {
        $user_id = session('user_id');
        $role_slug = session('role_slug');

        $mautic_settings = [
            ['name' => 'mautic_lead_lookup_enabled', 'value' => setting('mautic_lead_lookup_enabled', '0')],
            ['name' => 'mautic_lookup_mode', 'value' => setting('mautic_lookup_mode', 'api')],
            ['name' => 'mautic_api_url', 'value' => setting('mautic_api_url', '')],
            ['name' => 'mautic_api_username', 'value' => setting('mautic_api_username', '')],
            ['name' => 'mautic_api_password', 'value' => setting('mautic_api_password', '')],
            ['name' => 'mautic_lead_lookup_url', 'value' => setting('mautic_lead_lookup_url', '')],
        ];

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'mautic_settings' => filter_sensitive_settings($mautic_settings),
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/mautic_settings');
    }

    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('mautic_settings', 'array|null');

            $mautic_settings = request('mautic_settings', []);

            foreach ($mautic_settings as $mautic_setting) {
                $name = $mautic_setting['name'] ?? null;
                $value = $mautic_setting['value'] ?? null;

                if ($name === null) {
                    continue;
                }

                // Keep the existing password when the password field is left blank.
                if ($name === 'mautic_api_password' && ($value === null || $value === '')) {
                    continue;
                }

                setting([
                    $name => $value,
                ]);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
