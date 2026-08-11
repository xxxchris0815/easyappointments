<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

/**
 * Zoom_settings controller.
 *
 * Handles Zoom integration settings.
 *
 * @package Controllers
 */
class Zoom_settings extends EA_Controller
{
    /**
     * Zoom_settings constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');

        $role_slug = session('role_slug');

        $required_permissions = can('edit', PRIV_SYSTEM_SETTINGS);

        if ($required_permissions === false) {
            show_error('Forbidden', 403);
        }
    }

    /**
     * Render the settings page.
     */
    public function index(): void
    {
        $user_id = session('user_id');

        $role_slug = session('role_slug');

        $zoom_settings = [
            [
                'name' => 'zoom_enabled',
                'value' => setting('zoom_enabled', '0'),
            ],
            [
                'name' => 'zoom_account_id',
                'value' => setting('zoom_account_id', ''),
            ],
            [
                'name' => 'zoom_client_id',
                'value' => setting('zoom_client_id', ''),
            ],
            [
                'name' => 'zoom_client_secret',
                'value' => setting('zoom_client_secret', ''),
            ],
            [
                'name' => 'zoom_store_join_url_in_location',
                'value' => setting('zoom_store_join_url_in_location', '0'),
            ],
        ];

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'zoom_settings' => $zoom_settings,
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/zoom_settings');
    }

    /**
     * Save the Zoom settings.
     */
    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('zoom_settings', 'array|null');

            $zoom_settings = request('zoom_settings', []);

            foreach ($zoom_settings as $zoom_setting) {
                setting([
                    $zoom_setting['name'] => $zoom_setting['value'],
                ]);
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
