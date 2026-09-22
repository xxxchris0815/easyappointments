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

use Google\Service\Calendar\Event;
use Google\Service\Calendar\Events;

/**
 * Google sync library.
 *
 * Handles Google Calendar API related functionality.
 *
 * @package Libraries
 */
class Google_sync
{
    /**
     * Private Google Calendar extended property: EA appointment ID.
     */
    public const EA_EXTENDED_APPOINTMENT_ID = 'ea_appointment_id';

    /**
     * Private Google Calendar extended property: origin marker.
     */
    public const EA_EXTENDED_ORIGIN = 'ea_origin';

    /**
     * Value written to ea_origin for events created by Easy!Appointments.
     */
    public const EA_ORIGIN_VALUE = 'easyappointments';

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * @var Google_Client
     */
    protected Google_Client $client;

    /**
     * @var Google_Service_Calendar
     */
    protected Google_Service_Calendar $service;

    /**
     * Google_sync constructor.
     *
     * This method initializes the Google client class and the Calendar service class so that they can be used by the
     * other methods.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->model('customers_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('services_model');

        if (is_app_installed()) {
            $this->initialize_clients();
        }
    }

    /**
     * Get the Google Client ID from database settings or config fallback.
     *
     * @return string
     */
    protected function get_client_id(): string
    {
        $setting_value = setting('google_client_id');

        if (!empty($setting_value)) {
            return $setting_value;
        }

        return config('google_client_id') ?: '';
    }

    /**
     * Get the Google Client Secret from database settings or config fallback.
     *
     * @return string
     */
    protected function get_client_secret(): string
    {
        $setting_value = setting('google_client_secret');

        if (!empty($setting_value)) {
            return $setting_value;
        }

        return config('google_client_secret') ?: '';
    }

    /**
     * Initialize the client, so that existing execution errors are not passed from one provider to another.
     */
    public function initialize_clients(): void
    {
        $http = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $this->client = new Google_Client();
        $this->client->setHttpClient($http);
        $this->client->setApplicationName('Easy!Appointments');
        $this->client->setClientId($this->get_client_id());
        $this->client->setClientSecret($this->get_client_secret());
        $this->client->setRedirectUri(site_url('google/oauth_callback'));
        $this->client->setPrompt('consent');
        $this->client->setAccessType('offline');
        $this->client->addScope([Google_Service_Calendar::CALENDAR]);

        $this->service = new Google_Service_Calendar($this->client);
    }

    /**
     * Get Google OAuth authorization url.
     *
     * This url must be used to redirect the user to the Google user consent page,
     * where the user grants access to his data for the Easy!Appointments app.
     *
     * @param string|null $state Optional state parameter for CSRF protection.
     */
    public function get_auth_url(?string $state = null): string
    {
        // Use the client's setState() so the state is correctly embedded by createAuthUrl()
        // rather than manually concatenated, which avoids encoding edge-cases.
        if ($state !== null) {
            $this->client->setState($state);
        }

        // The "max_auth_age" is needed because the user needs to always log in and not use an existing session.
        return $this->client->createAuthUrl() . '&max_auth_age=0';
    }

    /**
     * Authenticate the Google API usage.
     *
     * When the user grants consent for his data usage, Google is going to redirect the browser back to the given
     * redirect URL. There an authentication code is provided. Using this code, we can authenticate the API usage and
     * store the token information to the database.
     *
     * @param string $code
     *
     * @return array
     *
     * @throws Exception
     */
    public function authenticate(string $code): array
    {
        $response = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($response['error'])) {
            throw new RuntimeException(
                'Google Authentication Error (' . $response['error'] . '): ' . $response['error_description'],
            );
        }

        return $response;
    }

    /**
     * Refresh the Google Client access token.
     *
     * This method must be executed every time we need to make actions on a provider's Google Calendar account. A new
     * token is necessary and the only way to get it is to use the stored refresh token that was provided when the
     * provider granted consent to Easy!Appointments for use his Google Calendar account.
     *
     * @param string $refresh_token The provider's refresh token. This value is stored in the database and used every
     * time we need to make actions to his Google Calendar account.
     */
    public function refresh_token(string $refresh_token): void
    {
        $this->initialize_clients();

        $this->client->refreshToken($refresh_token);
    }

    /**
     * Add an appointment record to its providers Google Calendar account.
     *
     * This method checks whether the appointment's provider has enabled the Google Sync utility of Easy!Appointments
     * and the stored access token is still valid. If yes, the selected appointment record is going to be added to the
     * Google Calendar account.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @param array $settings Required settings.
     *
     * @return Event Returns the Google_Event class object.
     *
     * @throws Exception
     */
    public function add_appointment(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
    ): Event {
        $anonymize = $this->should_anonymize($provider);

        $event = new Google_Service_Calendar_Event();
        $event->setSummary($this->build_event_summary($service));
        $event->setDescription($anonymize ? '' : $appointment['notes']);
        $event->setLocation($appointment['location'] ?? $settings['company_name']);

        $timezone = new DateTimeZone($provider['timezone']);

        $is_all_day = $this->is_all_day_event($appointment['start_datetime'], $appointment['end_datetime']);

        $start = $this->build_event_datetime($appointment['start_datetime'], $timezone, $is_all_day);
        $event->setStart($start);

        $end = $this->build_event_datetime($appointment['end_datetime'], $timezone, $is_all_day, true);
        $event->setEnd($end);

        $event->attendees = [];

        $event_provider = new Google_Service_Calendar_EventAttendee();
        $event_provider->setDisplayName($provider['first_name'] . ' ' . $provider['last_name']);
        $event_provider->setEmail($provider['email']);
        $event->attendees[] = $event_provider;

        if (
            !$anonymize &&
            !empty($customer['first_name']) &&
            !empty($customer['last_name']) &&
            !empty($customer['email'])
        ) {
            $event_customer = new Google_Service_Calendar_EventAttendee();
            $event_customer->setDisplayName($customer['first_name'] . ' ' . $customer['last_name']);
            $event_customer->setEmail($customer['email']);
            $event->attendees[] = $event_customer;
        }

        $this->apply_ea_origin_metadata($event, (int) ($appointment['id'] ?? 0));

        // Add Google Meet conferencing if enabled
        if (filter_var(setting('google_meet_link_generation'), FILTER_VALIDATE_BOOLEAN)) {
            $conference_data = new Google_Service_Calendar_ConferenceData();
            $create_request = new Google_Service_Calendar_CreateConferenceRequest();
            $create_request->setRequestId(uniqid('meet_', true));
            $conference_solution_key = new Google_Service_Calendar_ConferenceSolutionKey();
            $conference_solution_key->setType('hangoutsMeet');
            $create_request->setConferenceSolutionKey($conference_solution_key);
            $conference_data->setCreateRequest($create_request);
            $event->setConferenceData($conference_data);
        }

        // Add the new event to the Google Calendar.
        $created_event = $this->service->events->insert($provider['settings']['google_calendar'], $event, [
            'conferenceDataVersion' => 1,
        ]);

        // If Google Meet was enabled and a link was generated, update the appointment's meeting_link
        if (
            filter_var(setting('google_meet_link_generation'), FILTER_VALIDATE_BOOLEAN) &&
            $created_event->getConferenceData() &&
            $created_event->getConferenceData()->getEntryPoints()
        ) {
            $entry_points = $created_event->getConferenceData()->getEntryPoints();
            foreach ($entry_points as $entry_point) {
                if ($entry_point->getEntryPointType() === 'video') {
                    $appointment['meeting_link'] = $entry_point->getUri();
                    $this->CI->appointments_model->save($appointment);
                    break;
                }
            }
        }

        return $created_event;
    }

    /**
     * Update an existing appointment that is already synced with Google Calendar.
     *
     * This method updates the Google Calendar event item that is connected with the provided appointment record of
     * Easy!Appointments.
     *
     * @param array $appointment Appointment data.
     * @param array $provider Provider data.
     * @param array $service Service data.
     * @param array $customer Customer data.
     * @parma array $settings Required settings.
     *
     * @return Event Returns the Google_Service_Calendar_Event class object.
     *
     * @throws Exception
     */
    public function update_appointment(
        array $appointment,
        array $provider,
        array $service,
        array $customer,
        array $settings,
    ): Event {
        $anonymize = $this->should_anonymize($provider);

        $event = $this->service->events->get(
            $provider['settings']['google_calendar'],
            $appointment['id_google_calendar'],
        );

        $event->setSummary($this->build_event_summary($service));
        $event->setDescription($anonymize ? '' : $appointment['notes']);
        $event->setLocation($appointment['location'] ?? $settings['company_name']);

        $timezone = new DateTimeZone($provider['timezone']);

        $is_all_day = $this->is_all_day_event($appointment['start_datetime'], $appointment['end_datetime']);

        $start = $this->build_event_datetime($appointment['start_datetime'], $timezone, $is_all_day);
        $event->setStart($start);

        $end = $this->build_event_datetime($appointment['end_datetime'], $timezone, $is_all_day, true);
        $event->setEnd($end);

        $event->attendees = [];

        $event_provider = new Google_Service_Calendar_EventAttendee();
        $event_provider->setDisplayName($provider['first_name'] . ' ' . $provider['last_name']);
        $event_provider->setEmail($provider['email']);
        $event->attendees[] = $event_provider;

        if (
            !$anonymize &&
            !empty($customer['first_name']) &&
            !empty($customer['last_name']) &&
            !empty($customer['email'])
        ) {
            $event_customer = new Google_Service_Calendar_EventAttendee();
            $event_customer->setDisplayName($customer['first_name'] . ' ' . $customer['last_name']);
            $event_customer->setEmail($customer['email']);
            $event->attendees[] = $event_customer;
        }

        $this->apply_ea_origin_metadata($event, (int) ($appointment['id'] ?? 0));

        // Add Google Meet conferencing if enabled and event doesn't already have one
        if (
            filter_var(setting('google_meet_link_generation'), FILTER_VALIDATE_BOOLEAN) &&
            !$event->getConferenceData()
        ) {
            $conference_data = new Google_Service_Calendar_ConferenceData();
            $create_request = new Google_Service_Calendar_CreateConferenceRequest();
            $create_request->setRequestId(uniqid('meet_', true));
            $conference_solution_key = new Google_Service_Calendar_ConferenceSolutionKey();
            $conference_solution_key->setType('hangoutsMeet');
            $create_request->setConferenceSolutionKey($conference_solution_key);
            $conference_data->setCreateRequest($create_request);
            $event->setConferenceData($conference_data);
        }

        $updated_event = $this->service->events->update(
            $provider['settings']['google_calendar'],
            $event->getId(),
            $event,
            ['conferenceDataVersion' => 1],
        );

        // If Google Meet was enabled and a link was generated, update the appointment's meeting_link
        if (
            filter_var(setting('google_meet_link_generation'), FILTER_VALIDATE_BOOLEAN) &&
            $updated_event->getConferenceData() &&
            $updated_event->getConferenceData()->getEntryPoints() &&
            empty($appointment['meeting_link'])
        ) {
            $entry_points = $updated_event->getConferenceData()->getEntryPoints();
            foreach ($entry_points as $entry_point) {
                if ($entry_point->getEntryPointType() === 'video') {
                    $appointment['meeting_link'] = $entry_point->getUri();
                    $this->CI->appointments_model->save($appointment);
                    break;
                }
            }
        }

        return $updated_event;
    }

    /**
     * Delete an existing appointment from Google Calendar.
     *
     * @param array $provider Provider data.
     * @param string $google_event_id The Google Calendar event ID to be removed.
     *
     * @throws \Google\Service\Exception
     */
    public function delete_appointment(array $provider, string $google_event_id): void
    {
        $this->service->events->delete($provider['settings']['google_calendar'], $google_event_id);
    }

    /**
     * Add unavailability period event to Google Calendar.
     *
     * @param array $provider Provider data.
     * @param array $unavailability Unavailable data.
     *
     * @return Google_Service_Calendar_Event Returns the Google event.
     *
     * @throws Exception
     */
    public function add_unavailability(array $provider, array $unavailability): Google_Service_Calendar_Event
    {
        $event = new Google_Service_Calendar_Event();
        $event->setSummary('Unavailable');
        $event->setDescription($unavailability['notes']);

        $timezone = new DateTimeZone($provider['timezone']);

        $is_all_day = $this->is_all_day_event($unavailability['start_datetime'], $unavailability['end_datetime']);

        $start = $this->build_event_datetime($unavailability['start_datetime'], $timezone, $is_all_day);
        $event->setStart($start);

        $end = $this->build_event_datetime($unavailability['end_datetime'], $timezone, $is_all_day, true);
        $event->setEnd($end);

        // Add the new event to the Google Calendar.
        return $this->service->events->insert($provider['settings']['google_calendar'], $event);
    }

    /**
     * Update Google Calendar unavailability period event.
     *
     * @param array $provider Provider data.
     * @param array $unavailability Unavailability data.
     *
     * @return Google_Service_Calendar_Event Returns the Google_Service_Calendar_Event object.
     *
     * @throws Exception
     */
    public function update_unavailability(array $provider, array $unavailability): Google_Service_Calendar_Event
    {
        $event = $this->service->events->get(
            $provider['settings']['google_calendar'],
            $unavailability['id_google_calendar'],
        );

        $event->setSummary('Unavailable');
        $event->setDescription($unavailability['notes']);

        $timezone = new DateTimeZone($provider['timezone']);

        $is_all_day = $this->is_all_day_event($unavailability['start_datetime'], $unavailability['end_datetime']);

        $start = $this->build_event_datetime($unavailability['start_datetime'], $timezone, $is_all_day);
        $event->setStart($start);

        $end = $this->build_event_datetime($unavailability['end_datetime'], $timezone, $is_all_day, true);
        $event->setEnd($end);

        return $this->service->events->update($provider['settings']['google_calendar'], $event->getId(), $event);
    }

    /**
     * Delete unavailability period event from Google Calendar.
     *
     * @param array $provider Provider data.
     * @param string $google_event_id Google Calendar event ID to be removed.
     *
     * @throws \Google\Service\Exception
     */
    public function delete_unavailability(array $provider, string $google_event_id): void
    {
        $this->service->events->delete($provider['settings']['google_calendar'], $google_event_id);
    }

    /**
     * Get a Google Calendar event.
     *
     * @param array $provider Provider data.
     * @param string $google_event_id Google Calendar event ID.
     *
     * @return Event Returns the Google Calendar event.
     *
     * @throws \Google\Service\Exception
     */
    public function get_event(array $provider, string $google_event_id): Event
    {
        return $this->service->events->get($provider['settings']['google_calendar'], $google_event_id);
    }

    /**
     * Get all the events between the sync period.
     *
     * @param string $google_calendar The name of the Google Calendar to be used.
     * @param string $start The start date of sync period.
     * @param string $end The end date of sync period.
     *
     * @return Events Returns a collection of events.
     *
     * @throws \Google\Service\Exception
     */
    public function get_sync_events(string $google_calendar, string $start, string $end): Events
    {
        $params = [
            'timeMin' => date(DateTimeInterface::RFC3339, $start),
            'timeMax' => date(DateTimeInterface::RFC3339, $end),
            'singleEvents' => true,
            'maxResults' => 2500,
        ];

        $events = $this->service->events->listEvents($google_calendar, $params);
        $all_items = $events->getItems();

        // Iterate through additional pages because the Google Calendar API may
        // return fewer events than requested along with a non-empty
        // nextPageToken (e.g. when singleEvents=true expands recurring events).
        // Without this loop, calendars with more events than fit on a single
        // page silently lose the remaining events, so they are never written
        // as unavailabilities and the corresponding slots remain bookable.
        // A safety bound of 50 pages (~125000 events at the 2500 page size)
        // protects against pathological responses such as a circular
        // nextPageToken.
        $max_pages = 50;
        $page = 0;
        $page_token = $events->getNextPageToken();

        while (!empty($page_token) && $page < $max_pages) {
            $page++;
            $params['pageToken'] = $page_token;
            $next = $this->service->events->listEvents($google_calendar, $params);

            foreach ($next->getItems() as $item) {
                $all_items[] = $item;
            }

            $page_token = $next->getNextPageToken();
        }

        if (!empty($page_token)) {
            log_message(
                'error',
                'Google_sync::get_sync_events - reached the ' .
                    $max_pages .
                    '-page safety bound for calendar ' .
                    $google_calendar .
                    '; some events may be missing from the sync.',
            );
        }

        $events->setItems($all_items);

        return $events;
    }

    /**
     * Delete Google Calendar events titled "Unavailable" within the provider sync window.
     *
     * These leftovers come from the old bidirectional sync that pushed unavailabilities
     * back to Google as blockers. Re-importing them into EA creates duplicate
     * "Nichtverfügbarkeit" / "Unavailability" rows.
     *
     * @param array $provider Provider data (must include settings with google_calendar and sync window).
     *
     * @return array{deleted: int, scanned: int, errors: string[]}
     *
     * @throws InvalidArgumentException
     * @throws \Google\Service\Exception
     */
    public function remove_unavailable_events(array $provider): array
    {
        $google_calendar = $provider['settings']['google_calendar'] ?? null;

        if (empty($google_calendar)) {
            throw new InvalidArgumentException('Provider has no Google Calendar selected.');
        }

        [$sync_past_days, $sync_future_days] = calendar_sync_window_days($provider);

        $start = strtotime('-' . $sync_past_days . ' days', strtotime(date('Y-m-d')));
        $end = strtotime('+' . $sync_future_days . ' days', strtotime(date('Y-m-d')));

        $params = [
            'timeMin' => date(DateTimeInterface::RFC3339, $start),
            'timeMax' => date(DateTimeInterface::RFC3339, $end),
            'singleEvents' => true,
            'q' => 'Unavailable',
            'maxResults' => 250,
        ];

        $deleted = 0;
        $scanned = 0;
        $errors = [];
        $page_token = null;
        $max_pages = 50;
        $page = 0;

        do {
            if (!empty($page_token)) {
                $params['pageToken'] = $page_token;
            } else {
                unset($params['pageToken']);
            }

            $events = $this->service->events->listEvents($google_calendar, $params);

            foreach ($events->getItems() ?? [] as $event) {
                $scanned++;

                if (!$this->is_synthetic_unavailable_event($event)) {
                    continue;
                }

                if ($event->getStatus() === 'cancelled') {
                    continue;
                }

                try {
                    $this->service->events->delete($google_calendar, $event->getId());
                    $deleted++;
                } catch (Throwable $e) {
                    $errors[] = $event->getId() . ': ' . $e->getMessage();
                }
            }

            $page_token = $events->getNextPageToken();
            $page++;
        } while (!empty($page_token) && $page < $max_pages);

        if (!empty($page_token)) {
            log_message(
                'error',
                'Google_sync::remove_unavailable_events - reached the ' .
                    $max_pages .
                    '-page safety bound for calendar ' .
                    $google_calendar .
                    '; some events may remain.',
            );
        }

        return [
            'deleted' => $deleted,
            'scanned' => $scanned,
            'errors' => $errors,
        ];
    }

    /**
     * Return available Google Calendars for specific user.
     *
     * The given user's token must already exist in db in order to get access to his
     * Google Calendar account.
     *
     * @return array Returns an array with the available calendars.
     *
     * @throws \Google\Service\Exception
     */
    public function get_google_calendars(): array
    {
        $calendar_list = $this->service->calendarList->listCalendarList();

        $calendars = [];

        foreach ($calendar_list->getItems() as $google_calendar) {
            if ($google_calendar->getAccessRole() === 'reader') {
                continue;
            }

            $calendars[] = [
                'id' => $google_calendar->getId(),
                'summary' => $google_calendar->getSummary(),
            ];
        }

        usort($calendars, fn(array $a, array $b) => strcasecmp($a['summary'] ?? '', $b['summary'] ?? ''));

        return $calendars;
    }

    /**
     * Get the Add-To-Google-URL, that can be used by anyone to quickly add the event to Google Calendar (no API needed).
     *
     * @param int $appointment_id
     *
     * @return string
     *
     * @throws Exception
     */
    public function get_add_to_google_url(int $appointment_id): string
    {
        $appointment = $this->CI->appointments_model->find($appointment_id);

        $service = $this->CI->services_model->find($appointment['id_services']);

        $provider = $this->CI->providers_model->find($appointment['id_users_provider']);

        $customer = $this->CI->customers_model->find($appointment['id_users_customer']);

        $provider_timezone_instance = new DateTimeZone($provider['timezone']);
        $utc_timezone_instance = new DateTimeZone('UTC');

        $appointment_start_instance = new DateTime($appointment['start_datetime'], $provider_timezone_instance);
        $appointment_start_instance->setTimezone($utc_timezone_instance);

        $appointment_end_instance = new DateTime($appointment['end_datetime'], $provider_timezone_instance);
        $appointment_end_instance->setTimezone($utc_timezone_instance);

        // Collect invitees
        $add = [$provider['email']];
        if (!empty($customer['email'])) {
            $add[] = $customer['email'];
        }

        // Base params (everything except add)
        $params = [
            'action' => 'TEMPLATE',
            'text' => $service['name'],
            'dates' =>
                $appointment_start_instance->format('Ymd\THis\Z') .
                '/' .
                $appointment_end_instance->format('Ymd\THis\Z'),
            'location' => setting('company_name'),
            'details' => 'View/Change Appointment: ' . site_url('booking/reschedule/' . $appointment['hash']),
        ];

        // Build base query
        $query = http_build_query($params);

        // Append each guest separately
        foreach ($add as $email) {
            $query .= '&add=' . rawurlencode($email);
        }

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    /**
     * Check whether a start/end datetime pair should be pushed to Google as an all-day event.
     *
     * An event is treated as all-day when its start time is 00:00 and its end time is 23:59,
     * which is how EA stores events that were originally imported from Google as all-day events.
     */
    private function is_all_day_event(string $start_datetime, string $end_datetime): bool
    {
        return (new DateTime($start_datetime))->format('H:i') === '00:00' &&
            (new DateTime($end_datetime))->format('H:i') === '23:59';
    }

    /**
     * Build a Google Calendar EventDateTime for a given datetime string.
     *
     * When the event qualifies as all-day (00:00→23:59), sets only the date so that Google
     * Calendar renders it as an all-day block. For all-day events Google uses an exclusive
     * end date, so the end must be advanced by one day (e.g. 23:59 on the 27th → end.date = 28th).
     * For timed events the full RFC3339 datetime (timezone-aware) is used instead.
     *
     * @param string $datetime     Datetime string (Y-m-d H:i:s).
     * @param DateTimeZone $timezone  Provider timezone.
     * @param bool $is_all_day     Whether to use date-only format.
     * @param bool $is_end         For all-day end: advance by one day to make it exclusive.
     */
    private function build_event_datetime(
        string $datetime,
        DateTimeZone $timezone,
        bool $is_all_day,
        bool $is_end = false,
    ): Google_Service_Calendar_EventDateTime {
        $event_dt = new Google_Service_Calendar_EventDateTime();

        if ($is_all_day) {
            $dt = new DateTime($datetime, $timezone);

            if ($is_end) {
                $dt->modify('+1 day'); // Google's all-day end is exclusive
            }

            $event_dt->setDate($dt->format('Y-m-d'));
        } else {
            $event_dt->setDateTime(
                (new DateTime($datetime, $timezone))->format(DateTimeInterface::RFC3339),
            );
        }

        return $event_dt;
    }

    /**
     * Mark a Google event as originating from an Easy!Appointments booking.
     *
     * Used to avoid re-importing EA→Google events as Unavailabilities.
     */
    public function apply_ea_origin_metadata($event, int $appointment_id): void
    {
        if ($appointment_id <= 0 || !is_object($event) || !method_exists($event, 'setExtendedProperties')) {
            return;
        }

        $private = [
            self::EA_EXTENDED_APPOINTMENT_ID => (string) $appointment_id,
            self::EA_EXTENDED_ORIGIN => self::EA_ORIGIN_VALUE,
        ];

        $existing = method_exists($event, 'getExtendedProperties') ? $event->getExtendedProperties() : null;

        if ($existing && method_exists($existing, 'getPrivate')) {
            $existing_private = $existing->getPrivate() ?: [];
            if (is_array($existing_private)) {
                $private = array_merge($existing_private, $private);
            }
        }

        $extended = new Google_Service_Calendar_EventExtendedProperties();
        $extended->setPrivate($private);
        $event->setExtendedProperties($extended);
    }

    /**
     * Read the EA appointment ID stored on a Google event, if any.
     */
    public function get_ea_appointment_id_from_event($event): ?int
    {
        if (!is_object($event) || !method_exists($event, 'getExtendedProperties')) {
            return null;
        }

        $extended = $event->getExtendedProperties();

        if (!$extended || !method_exists($extended, 'getPrivate')) {
            return null;
        }

        $private = $extended->getPrivate();

        if (!is_array($private)) {
            return null;
        }

        $raw = $private[self::EA_EXTENDED_APPOINTMENT_ID] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $id = filter_var($raw, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? (int) $id : null;
    }

    /**
     * Whether a Google event was created/updated by Easy!Appointments.
     */
    public function is_ea_origin_event($event): bool
    {
        if ($this->get_ea_appointment_id_from_event($event) !== null) {
            return true;
        }

        if (!is_object($event) || !method_exists($event, 'getExtendedProperties')) {
            return false;
        }

        $extended = $event->getExtendedProperties();

        if (!$extended || !method_exists($extended, 'getPrivate')) {
            return false;
        }

        $private = $extended->getPrivate();

        if (!is_array($private)) {
            return false;
        }

        return ($private[self::EA_EXTENDED_ORIGIN] ?? null) === self::EA_ORIGIN_VALUE;
    }

    /**
     * Extract provider-local start/end timestamps from a Google event.
     *
     * @return array{0:int,1:int}|null
     */
    public function extract_event_range($google_event, DateTimeZone $provider_timezone): ?array
    {
        if (
            !is_object($google_event) ||
            !method_exists($google_event, 'getStart') ||
            !method_exists($google_event, 'getEnd') ||
            $google_event->getStart() === null ||
            $google_event->getEnd() === null
        ) {
            return null;
        }

        $is_all_day = $google_event->getStart()->getDateTime() === null;

        if ($is_all_day) {
            $g_start = new DateTime($google_event->getStart()->getDate() . ' 00:00:00', $provider_timezone);
            $g_end = new DateTime($google_event->getEnd()->getDate() . ' 00:00:00', $provider_timezone);
            $g_end->modify('-1 minute');
        } else {
            if ($google_event->getStart()->getDateTime() === $google_event->getEnd()->getDateTime()) {
                return null;
            }

            $g_start = new DateTime($google_event->getStart()->getDateTime());
            $g_start->setTimezone($provider_timezone);
            $g_end = new DateTime($google_event->getEnd()->getDateTime());
            $g_end->setTimezone($provider_timezone);
        }

        return [$g_start->getTimestamp(), $g_end->getTimestamp()];
    }

    /**
     * Determine whether Google Calendar event details should be hidden in Easy!Appointments.
     */
    public function should_anonymize(array $provider): bool
    {
        $global = filter_var(setting('google_calendar_anonymize'), FILTER_VALIDATE_BOOLEAN);
        $provider_flag = filter_var($provider['settings']['google_calendar_anonymize'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $global || $provider_flag;
    }

    /**
     * Whether a Google event is a leftover blocker that EA itself once pushed as "Unavailable".
     *
     * Old sync versions wrote Unavailabilities back to Google under this fixed summary.
     * Those events must not be re-imported as EA busy blocks (causes duplicate rows).
     */
    public function is_synthetic_unavailable_event($event): bool
    {
        if (!is_object($event) || !method_exists($event, 'getSummary')) {
            return false;
        }

        return strcasecmp(trim((string) $event->getSummary()), 'Unavailable') === 0;
    }

    /**
     * Whether two half-open time ranges overlap.
     */
    public function ranges_overlap(int $start_a, int $end_a, int $start_b, int $end_b): bool
    {
        return $start_a < $end_b && $end_a > $start_b;
    }

    /**
     * Notes stored in EA for an imported Google event (summary + description, or empty when anonymized).
     */
    public function build_imported_event_notes($google_event, array $provider): string
    {
        if ($this->should_anonymize($provider)) {
            return '';
        }

        $summary = trim((string) $google_event->getSummary());
        $description = (string) $google_event->getDescription();

        // Skip the synthetic "Unavailable" summary that EA itself sets when
        // pushing unavailabilities to Google so it doesn't get duplicated into notes.
        if (strcasecmp($summary, 'Unavailable') === 0) {
            return $description;
        }

        return trim($summary . ' ' . $description);
    }

    /**
     * Build the Google Calendar event summary for appointments pushed from EA.
     */
    private function build_event_summary(array $service): string
    {
        return !empty($service['name']) ? $service['name'] : 'Unavailable';
    }
}
