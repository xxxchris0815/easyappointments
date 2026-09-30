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

if (!function_exists('setting')) {
    /**
     * Get / set the specified setting value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * Example "Get":
     *
     * $company_name = session('company_name', FALSE);
     *
     * Example "Set":
     *
     * setting(['company_name' => 'ACME Inc']);
     *
     * @param array|string|null $key Setting key.
     * @param mixed|null $default Default value in case the requested setting has no value.
     *
     * @return mixed|NULL Returns the requested value or NULL if you assign a new setting value.
     *
     * @throws InvalidArgumentException
     */
    function setting(array|string|null $key = null, mixed $default = null): mixed
    {
        /** @var EA_Controller $CI */
        $CI = &get_instance();

        $CI->load->model('settings_model');

        if (empty($key)) {
            throw new InvalidArgumentException('The $key argument cannot be empty.');
        }

        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $setting = $CI->settings_model->query()->where('name', $name)->get()->row_array();

                if (empty($setting)) {
                    $setting = [
                        'name' => $name,
                    ];
                }

                $setting['value'] = $value;

                $CI->settings_model->save($setting);
            }

            return null;
        }

        $setting = $CI->settings_model->query()->where('name', $key)->get()->row_array();

        return $setting['value'] ?? $default;
    }
}

if (!function_exists('calendar_sync_window_days')) {
    /**
     * Resolve calendar sync past/future days for a provider.
     *
     * Prefers the global Google Calendar Sync Window settings; falls back to
     * the provider user_settings values used by older installs.
     *
     * @param array $provider Provider record with optional settings keys.
     *
     * @return array{0:int,1:int} [past_days, future_days]
     */
    function calendar_sync_window_days(array $provider = []): array
    {
        $max_horizon_days = 400;
        $settings = $provider['settings'] ?? [];

        $past = (int) setting('google_sync_past_days', 0);
        $future = (int) setting('google_sync_future_days', 0);

        if ($past <= 0) {
            $past = (int) ($settings['sync_past_days'] ?? 30);
        }

        if ($future <= 0) {
            $future = (int) ($settings['sync_future_days'] ?? 90);
        }

        $past = max(1, min($max_horizon_days, $past));
        $future = max(1, min($max_horizon_days, $future));

        return [$past, $future];
    }
}
