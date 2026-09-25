<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Google_calendar_settings controller.
 *
 * Handles Google Calendar Sync integration settings.
 *
 * @package Controllers
 */
class Google_calendar_settings extends EA_Controller
{
    /**
     * Google_calendar_settings constructor.
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

        $google_calendar_settings = [
            [
                'name' => 'google_sync_feature',
                'value' => setting('google_sync_feature', '0'),
            ],
            [
                'name' => 'google_client_id',
                'value' => setting('google_client_id', ''),
            ],
            [
                'name' => 'google_client_secret',
                'value' => setting('google_client_secret', ''),
            ],
            [
                'name' => 'google_meet_link_generation',
                'value' => setting('google_meet_link_generation', '0'),
            ],
            [
                'name' => 'display_add_to_google_calendar',
                'value' => setting('display_add_to_google_calendar', '1'),
            ],
            [
                'name' => 'google_calendar_anonymize',
                'value' => setting('google_calendar_anonymize', '0'),
            ],
            [
                'name' => 'google_sync_past_days',
                'value' => setting('google_sync_past_days', '30'),
            ],
            [
                'name' => 'google_sync_future_days',
                'value' => setting('google_sync_future_days', '90'),
            ],
        ];

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'google_calendar_settings' => filter_sensitive_settings($google_calendar_settings),
        ]);

        html_vars([
            'page_title' => lang('settings'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
        ]);

        $this->load->view('pages/google_calendar_settings');
    }

    /**
     * Save the Google Calendar settings.
     */
    public function save(): void
    {
        try {
            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('google_calendar_settings', 'array|null');

            $google_calendar_settings = request('google_calendar_settings', []);
            $sync_past_days = null;
            $sync_future_days = null;

            foreach ($google_calendar_settings as $google_calendar_setting) {
                $name = (string) ($google_calendar_setting['name'] ?? '');
                $value = $google_calendar_setting['value'] ?? null;

                if ($name === 'google_sync_past_days') {
                    $sync_past_days = max(1, min(400, (int) $value));
                    $value = (string) $sync_past_days;
                }

                if ($name === 'google_sync_future_days') {
                    $sync_future_days = max(1, min(400, (int) $value));
                    $value = (string) $sync_future_days;
                }

                setting([
                    $name => $value,
                ]);
            }

            // Keep provider rows aligned so Sync Status / Diagnose match the global window.
            if ($sync_past_days !== null || $sync_future_days !== null) {
                $this->load->model('providers_model');

                foreach ($this->providers_model->get() as $provider) {
                    $provider_id = (int) ($provider['id'] ?? 0);

                    if ($provider_id <= 0) {
                        continue;
                    }

                    if ($sync_past_days !== null) {
                        $this->providers_model->set_setting($provider_id, 'sync_past_days', $sync_past_days);
                    }

                    if ($sync_future_days !== null) {
                        $this->providers_model->set_setting($provider_id, 'sync_future_days', $sync_future_days);
                    }
                }
            }

            response();
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
