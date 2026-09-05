<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Google controller.
 *
 * Handles the Google Calendar synchronization related operations.
 *
 * @package Controllers
 */
class Google extends EA_Controller
{
    /**
     * Google constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('google_sync');

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');
    }

    /**
     * Complete synchronization of appointments between Google Calendar and Easy!Appointments.
     *
     * This method will completely sync the appointments of a provider with his Google Calendar account. The sync period
     * needs to be relatively small, because a lot of API calls might be necessary and this will lead to consuming the
     * Google limit for the Calendar API usage.
     */
    public static function sync(?string $provider_id = null): void
    {
        try {
            /** @var EA_Controller $CI */
            $CI = get_instance();

            $CI->load->library('google_sync');

            // Load the libraries as this method is called statically from the CLI command

            $CI->load->model('appointments_model');
            $CI->load->model('unavailabilities_model');
            $CI->load->model('providers_model');
            $CI->load->model('services_model');
            $CI->load->model('customers_model');
            $CI->load->model('settings_model');

            $user_id = session('user_id');

            if (!$user_id && !is_cli()) {
                return;
            }

            if (!$provider_id) {
                throw new InvalidArgumentException('No provider ID provided.');
            }

            $provider = $CI->providers_model->find($provider_id);

            // Check whether the selected provider has the Google Sync enabled.
            $google_sync = $CI->providers_model->get_setting($provider['id'], 'google_sync');

            if (!$google_sync) {
                return; // The selected provider does not have the Google Sync enabled.
            }

            $google_token = json_decode($provider['settings']['google_token'], true);

            $CI->google_sync->refresh_token($google_token['refresh_token']);

            // Fetch provider's appointments that belong to the sync time period.
            $sync_past_days = $provider['settings']['sync_past_days'];

            $sync_future_days = $provider['settings']['sync_future_days'];

            $start = strtotime('-' . $sync_past_days . ' days', strtotime(date('Y-m-d')));

            $end = strtotime('+' . $sync_future_days . ' days', strtotime(date('Y-m-d')));

            $where = [
                'start_datetime >=' => date('Y-m-d H:i:s', $start),
                'end_datetime <=' => date('Y-m-d H:i:s', $end),
                'id_users_provider' => $provider['id'],
            ];

            $appointments = $CI->appointments_model->get($where);

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
            ];

            $provider_timezone = new DateTimeZone($provider['timezone']);

            // One-way sync: only push Easy!Appointments bookings to Google.
            // Do not import personal Google events into EA, and do not push unavailabilities
            // (that previously created duplicate "Unavailable" blockers in Google).
            try {
                $existing_google_events = $CI->google_sync->get_sync_events(
                    $provider['settings']['google_calendar'],
                    $start,
                    $end,
                );
            } catch (Throwable) {
                $existing_google_events = null;
            }

            $extract_google_event_range = function ($google_event) use ($provider_timezone): ?array {
                if ($google_event->getStart() === null || $google_event->getEnd() === null) {
                    return null;
                }

                $is_all_day = $google_event->getStart()->getDateTime() === null;

                if ($is_all_day) {
                    $g_start = new DateTime($google_event->getStart()->getDate() . ' 00:00:00', $provider_timezone);
                    $g_end = new DateTime($google_event->getEnd()->getDate() . ' 00:00:00', $provider_timezone);
                    $g_end->modify('-1 minute');
                } else {
                    $g_start = new DateTime($google_event->getStart()->getDateTime());
                    $g_start->setTimezone($provider_timezone);
                    $g_end = new DateTime($google_event->getEnd()->getDateTime());
                    $g_end->setTimezone($provider_timezone);
                }

                return [$g_start->getTimestamp(), $g_end->getTimestamp()];
            };

            foreach ($appointments as $appointment) {
                $service = $CI->services_model->find($appointment['id_services']);
                $customer = $CI->customers_model->find($appointment['id_users_customer']);
                $service_name = trim((string) ($service['name'] ?? ''));

                // Not yet linked: create on Google (or re-link an EA-created event after sync toggle).
                if (empty($appointment['id_google_calendar'])) {
                    $matched_google_event = null;

                    if ($existing_google_events !== null && $service_name !== '') {
                        $local_start_ts = (new DateTime($appointment['start_datetime'], $provider_timezone))
                            ->getTimestamp();
                        $local_end_ts = (new DateTime($appointment['end_datetime'], $provider_timezone))
                            ->getTimestamp();

                        foreach ($existing_google_events->getItems() as $candidate) {
                            if ($candidate->getStatus() === 'cancelled') {
                                continue;
                            }

                            $candidate_range = $extract_google_event_range($candidate);

                            if ($candidate_range === null) {
                                continue;
                            }

                            if (
                                $candidate_range[0] !== $local_start_ts ||
                                $candidate_range[1] !== $local_end_ts
                            ) {
                                continue;
                            }

                            // Only re-link events that look like EA bookings (service name title),
                            // never personal Google events.
                            if (strcasecmp(trim((string) $candidate->getSummary()), $service_name) !== 0) {
                                continue;
                            }

                            $matched_google_event = $candidate;
                            break;
                        }
                    }

                    if ($matched_google_event !== null) {
                        $appointment = $CI->appointments_model->find($appointment['id']);
                        $appointment['id_google_calendar'] = $matched_google_event->getId();
                        $CI->appointments_model->save($appointment);
                        continue;
                    }

                    $google_event = $CI->google_sync->add_appointment(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                    );

                    $appointment = $CI->appointments_model->find($appointment['id']);
                    $appointment['id_google_calendar'] = $google_event->getId();
                    $CI->appointments_model->save($appointment);

                    continue;
                }

                // Already linked: push EA → Google only (never overwrite EA from personal Google data).
                try {
                    $google_event = $CI->google_sync->get_event($provider, $appointment['id_google_calendar']);

                    if ($google_event->getStatus() === 'cancelled') {
                        $appointment = $CI->appointments_model->find($appointment['id']);
                        if ($appointment) {
                            $appointment['id_google_calendar'] = null;
                            $CI->appointments_model->save($appointment);
                        }
                        continue;
                    }

                    $CI->google_sync->update_appointment($appointment, $provider, $service, $customer, $settings);
                } catch (Throwable $e) {
                    $code = (int) $e->getCode();

                    if ($code === 404) {
                        $appointment = $CI->appointments_model->find($appointment['id']);
                        if ($appointment) {
                            $appointment['id_google_calendar'] = null;
                            $CI->appointments_model->save($appointment);
                        }
                        log_message(
                            'error',
                            'Google - Unlinked appointment ID ' .
                                ($appointment['id'] ?? '?') .
                                ' because the remote event was not found.',
                        );
                    } else {
                        log_message(
                            'error',
                            'Google - Skipped sync for appointment ID ' .
                                ($appointment['id'] ?? '?') .
                                ': ' .
                                $e->getMessage(),
                        );
                    }
                }
            }

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Google - Sync completed with an error (provider ID "' . $provider_id . '"): ' . $e->getMessage(),
            );

            if ($e->getCode() === 401) {
                json_response(
                    [
                        'success' => false,
                        'message' => lang('invalid_credentials_provided'),
                    ],
                    401,
                );

                return;
            }

            json_exception($e);
        }
    }

    /**
     * Authorize Google Calendar API usage for a specific provider.
     *
     * Since it is required to follow the web application flow, in order to retrieve a refresh token from the Google API
     * service, this method is going to authorize the given provider.
     *
     * @param string $provider_id The provider id, for whom the sync authorization is made.
     */
    public function oauth(string $provider_id): void
    {
        $user_id = session('user_id');

        if (!$user_id) {
            show_error('Forbidden', 403);
        }

        // Validate provider_id is a positive integer
        $provider_id = filter_var($provider_id, FILTER_VALIDATE_INT);
        if ($provider_id === false || $provider_id <= 0) {
            show_error('Invalid provider ID', 400);
        }

        if (cannot('edit', PRIV_USERS) && (int) $user_id !== (int) $provider_id) {
            show_error('Forbidden', 403);
        }

        // Generate and store OAuth state parameter to prevent CSRF
        $oauth_state = bin2hex(random_bytes(32));

        // Store the provider id and state for use on the callback function.
        session([
            'oauth_provider_id' => $provider_id,
            'oauth_state' => $oauth_state,
        ]);

        // Redirect browser to google user content page.
        header('Location: ' . $this->google_sync->get_auth_url($oauth_state));
    }

    /**
     * Callback method for the Google Calendar API authorization process.
     *
     * Once the user grants consent with his Google Calendar data usage, the Google OAuth service will redirect him back
     * in this page. Here we are going to store the refresh token, because this is what will be used to generate access
     * tokens in the future.
     *
     * IMPORTANT: Because it is necessary to authorize the application using the web server flow (see official
     * documentation of OAuth), every Easy!Appointments installation should use its own calendar api key. So in every
     * api console account, the "http://path-to-Easy!Appointments/google/oauth_callback" should be included in an
     * allowed redirect URL.
     *
     * @throws Exception
     */
    public function oauth_callback(): void
    {
        if (!session('user_id')) {
            abort(403, 'Forbidden');
        }

        // Verify OAuth state to prevent CSRF attacks. If state is absent (e.g. a stale redirect
        // from before CSRF protection was added) or mismatched, abort gracefully.
        $returned_state = request('state');
        $stored_state = session('oauth_state');

        if (empty($returned_state) || empty($stored_state) || !hash_equals($stored_state, $returned_state)) {
            session(['oauth_state' => null]);
            show_error('Security validation failed. Please try the Google Calendar sync again.', 403);

            return;
        }

        // Clear the state after verification
        session(['oauth_state' => null]);

        $code = request('code');

        if (empty($code)) {
            response('Code authorization failed.');

            return;
        }

        $token = $this->google_sync->authenticate($code);

        if (empty($token)) {
            response('Token authorization failed.');

            return;
        }

        // Store the token into the database for future reference.
        $oauth_provider_id = filter_var(session('oauth_provider_id'), FILTER_VALIDATE_INT);
        $user_id = (int) session('user_id');

        if ($oauth_provider_id && $oauth_provider_id > 0) {
            if (cannot('edit', PRIV_USERS) && $user_id !== (int) $oauth_provider_id) {
                show_error('Forbidden', 403);

                return;
            }

            $this->providers_model->set_setting($oauth_provider_id, 'google_sync', true);
            $this->providers_model->set_setting($oauth_provider_id, 'google_token', json_encode($token));
            $this->providers_model->set_setting($oauth_provider_id, 'google_calendar', 'primary');
            session(['oauth_provider_id' => null]);

            // Notify the opener that OAuth completed successfully, then close this popup. Using
            // postMessage ensures the parent only reacts AFTER the server has saved the token,
            // avoiding the race condition that arises when polling window.document.URL.
            echo '<script>window.opener && window.opener.postMessage("oauth_success", window.location.origin); window.close();</script>';
        } else {
            response('Sync provider id not specified.');
        }
    }

    /**
     * This method will return a list of the available Google Calendars.
     *
     * The user will need to select a specific calendar from this list to sync his appointments with. Google access must
     * be already granted for the specific provider.
     */
    public function get_google_calendars(): void
    {
        try {
            method('post');

            check('provider_id', 'numeric');

            $provider_id = (int) request('provider_id');

            if (empty($provider_id)) {
                throw new Exception('Provider id is required in order to fetch the google calendars.');
            }

            // Check if selected provider has sync enabled.
            $google_sync = $this->providers_model->get_setting($provider_id, 'google_sync');

            if (!$google_sync) {
                json_response([
                    'success' => false,
                ]);

                return;
            }

            $google_token = json_decode($this->providers_model->get_setting($provider_id, 'google_token'), true);

            $this->google_sync->refresh_token($google_token['refresh_token']);

            $calendars = $this->google_sync->get_google_calendars();

            json_response($calendars);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Select a specific google calendar for a provider.
     *
     * All the appointments will be synced with this particular calendar.
     */
    public function select_google_calendar(): void
    {
        try {
            method('post');

            check('provider_id', 'numeric');
            check('calendar_id', 'string');

            $provider_id = request('provider_id');

            $user_id = session('user_id');

            if (cannot('edit', PRIV_USERS) && (int) $user_id !== (int) $provider_id) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $calendar_id = request('calendar_id');

            $this->providers_model->set_setting($provider_id, 'google_calendar', $calendar_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Disable a providers sync setting.
     *
     * This method deletes the "google_sync" and "google_token" settings from the database.
     *
     * After that the provider's appointments will be no longer synced with Google Calendar.
     */
    public function disable_provider_sync(): void
    {
        try {
            method('post');

            check('provider_id', 'numeric');

            $provider_id = request('provider_id');

            if (!$provider_id) {
                throw new Exception('Provider id not specified.');
            }

            $user_id = session('user_id');

            if (cannot('edit', PRIV_USERS) && (int) $user_id !== (int) $provider_id) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $this->providers_model->set_setting($provider_id, 'google_sync', false);

            $this->providers_model->set_setting($provider_id, 'google_token');

            $this->appointments_model->clear_google_sync_ids($provider_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
