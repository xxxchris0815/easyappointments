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
 * Calendar controller.
 *
 * Handles calendar related operations.
 *
 * @package Controllers
 */
class Calendar extends EA_Controller
{
    public array $allowed_customer_fields = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'address',
        'city',
        'state',
        'zip_code',
        'timezone',
        'language',
        'notes',
    ];

    public array $optional_customer_fields = [
        //
    ];

    public array $allowed_appointment_fields = [
        'id',
        'start_datetime',
        'end_datetime',
        'location',
        'meeting_link',
        'id_zoom_meeting',
        'notes',
        'color',
        'status',
        'is_unavailability',
        'id_users_provider',
        'id_users_customer',
        'id_users_created_by',
        'id_services',
    ];

    public array $optional_appointment_fields = [
        //
    ];

    /**
     * Calendar constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('unavailabilities_model');
        $this->load->model('blocked_periods_model');
        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('google_sync');
        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('timezones');
        $this->load->library('webhooks_client');
        $this->load->library('permissions');
        $this->load->library('jitsi_client');
        $this->load->library('zoom_client');
        $this->load->model('secretaries_model');

        for ($i = 1; $i <= max_custom_fields(); $i++) {
            $this->allowed_customer_fields[] = 'custom_field_' . $i;
        }
    }

    /**
     * Render the calendar page and display the selected appointment.
     *
     * This method will call the "index" callback to handle the page rendering.
     *
     * @param string $appointment_hash Appointment hash.
     */
    public function reschedule(string $appointment_hash): void
    {
        $this->index($appointment_hash);
    }

    /**
     * Display the main backend page.
     *
     * This method displays the main backend page. All login permission can view this page which displays a calendar
     * with the events of the selected provider or service. If a user has more privileges he will see more menus at the
     * top of the page.
     *
     * @param string $appointment_hash Appointment hash.
     */
    public function index(string $appointment_hash = ''): void
    {
        method('get');

        session([
            'dest_url' => site_url('calendar/index' . (!empty($appointment_hash) ? '/' . $appointment_hash : '')),
        ]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        $user = $this->users_model->find($user_id);

        $secretary_providers = [];

        if ($role_slug === DB_SLUG_SECRETARY) {
            $secretary = $this->secretaries_model->find(session('user_id'));

            $secretary_providers = $secretary['providers'];
        }

        $edit_appointment = null;

        if (!empty($appointment_hash)) {
            $occurrences = $this->appointments_model->get(['hash' => $appointment_hash]);

            if ($appointment_hash !== '' && !empty($occurrences)) {
                $edit_appointment = $occurrences[0];

                $this->appointments_model->load($edit_appointment, ['customer']);
            }
        }

        $privileges = $this->roles_model->get_permissions_by_slug($role_slug);

        $available_providers = $this->providers_model->get_available_providers();

        if ($role_slug === DB_SLUG_PROVIDER) {
            $available_providers = array_values(
                array_filter($available_providers, function ($available_provider) use ($user_id) {
                    return (int) $available_provider['id'] === (int) $user_id;
                }),
            );
        }

        if ($role_slug === DB_SLUG_SECRETARY) {
            $available_providers = array_values(
                array_filter($available_providers, function ($available_provider) use ($secretary_providers) {
                    return in_array($available_provider['id'], $secretary_providers);
                }),
            );
        }

        $available_services = $this->services_model->get_available_services();

        // Filter services to only include those that can be served by at least one available provider
        $provider_service_ids = [];
        foreach ($available_providers as $provider) {
            foreach ($provider['services'] as $service_id) {
                $provider_service_ids[$service_id] = true;
            }
        }

        $available_services = array_values(
            array_filter($available_services, function ($service) use ($provider_service_ids) {
                return isset($provider_service_ids[$service['id']]);
            }),
        );

        $calendar_view = request('view', $user['settings']['calendar_view']);

        $appointment_status_options = setting('appointment_status_options');

        $customers = $this->customers_model->get(null, 50, null, 'update_datetime DESC');

        if (setting('limit_customer_access') && $role_slug === DB_SLUG_PROVIDER) {
            // Only include the customers that the provider is supposed to see (they had past booking together)
            $CI = $this;

            $customers = array_values(
                array_filter($customers, function ($customer) use ($user_id, $CI) {
                    if (!$CI->permissions->has_customer_access($user_id, $customer['id'])) {
                        return false;
                    }

                    return true;
                }),
            );
        }

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
            'company_working_plan' => setting('company_working_plan'),
            'timezones' => $this->timezones->to_array(),
            'privileges' => $privileges,
            'calendar_view' => $calendar_view,
            'available_providers' => filter_sensitive_users_data($available_providers),
            'available_services' => $available_services,
            'secretary_providers' => $secretary_providers,
            'edit_appointment' => $edit_appointment,
            'google_sync_feature' => filter_var(
                setting('google_sync_feature') ?: config('google_sync_feature'),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'customers' => $customers,
            'default_language' => setting('default_language'),
            'default_timezone' => setting('default_timezone'),
            'calendar_modal_visible_fields' => json_decode(
                (string) setting('calendar_modal_visible_fields', '{}'),
                true,
            ) ?: [],
            'calendar_select_opens_appointment' => filter_var(
                setting('calendar_select_opens_appointment', '1'),
                FILTER_VALIDATE_BOOLEAN,
            ) ? 1 : 0,
            'calendar_provider_select_editable' => filter_var(
                setting('calendar_provider_select_editable', '1'),
                FILTER_VALIDATE_BOOLEAN,
            ) ? 1 : 0,
        ]);

        html_vars([
            'page_title' => lang('calendar'),
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'timezone' => session('timezone'),
            'timezones' => $this->timezones->to_array(),
            'grouped_timezones' => $this->timezones->to_grouped_array(),
            'privileges' => $privileges,
            'calendar_view' => $calendar_view,
            'available_providers' => filter_sensitive_users_data($available_providers),
            'available_services' => $available_services,
            'secretary_providers' => $secretary_providers,
            'appointment_status_options' => json_decode($appointment_status_options, true) ?? [],
            'require_first_name' => setting('require_first_name'),
            'require_last_name' => setting('require_last_name'),
            'require_email' => setting('require_email'),
            'require_phone_number' => setting('require_phone_number'),
            'require_address' => setting('require_address'),
            'require_city' => setting('require_city'),
            'require_zip_code' => setting('require_zip_code'),
            'require_notes' => setting('require_notes'),
            'calendar_modal_visible_fields' => json_decode(
                (string) setting('calendar_modal_visible_fields', '{}'),
                true,
            ) ?: [],
        ]);

        $this->load->view('pages/calendar');
    }

    /**
     * Save appointment changes that are made from the backend calendar page.
     */
    public function save_appointment(): void
    {
        try {
            method('post');

            check('customer_data', 'array|null');
            check('appointment_data', 'array');
            check('notify_users', 'bool|null');
            check('force_save', 'bool|null');

            $customer_data = request('customer_data');

            $appointment_data = request('appointment_data');

            $notify_users = filter_var(request('notify_users', true), FILTER_VALIDATE_BOOLEAN);

            $force_save = filter_var(request('force_save', false), FILTER_VALIDATE_BOOLEAN);

            $this->check_event_permissions((int) $appointment_data['id_users_provider']);

            if (!empty($appointment_data['id'])) {
                $existing_appointment = $this->appointments_model->find((int) $appointment_data['id']);
                $this->check_restricted_secretary_appointment_access($existing_appointment);
            }

            // Save customer changes to the database.
            if ($customer_data) {
                $customer = $customer_data;

                $required_permissions = !empty($customer['id'])
                    ? can('add', PRIV_CUSTOMERS)
                    : can('edit', PRIV_CUSTOMERS);

                if (!$required_permissions) {
                    throw new RuntimeException('You do not have the required permissions for this task.');
                }

                $this->customers_model->only($customer, $this->allowed_customer_fields);

                $this->customers_model->optional($customer, $this->optional_customer_fields);

                // Reuse existing customers by email (same behavior as public booking).
                if (empty($customer['id']) && $this->customers_model->exists($customer)) {
                    $customer['id'] = $this->customers_model->find_record_id($customer);
                }

                $customer['id'] = $this->customers_model->save($customer);
            }

            // Save appointment changes to the database.
            $manage_mode = !empty($appointment_data['id']);

            if ($appointment_data) {
                $appointment = $appointment_data;

                $required_permissions = !empty($appointment['id'])
                    ? can('add', PRIV_APPOINTMENTS)
                    : can('edit', PRIV_APPOINTMENTS);

                if (!$required_permissions) {
                    throw new RuntimeException('You do not have the required permissions for this task.');
                }

                // If the appointment does not contain the customer record id, then it means that is going to be inserted.

                if (!isset($appointment['id_users_customer'])) {
                    $appointment['id_users_customer'] = $customer['id'] ?? $customer_data['id'];
                }

                // Check if the provider has a conflicting appointment at the selected time
                $exclude_appointment_id = !empty($appointment['id']) ? (int) $appointment['id'] : null;

                $has_conflict = $this->appointments_model->has_provider_conflict(
                    (int) $appointment['id_users_provider'],
                    $appointment['start_datetime'],
                    $appointment['end_datetime'],
                    $exclude_appointment_id,
                );

                if ($has_conflict && !$force_save) {
                    json_response([
                        'success' => false,
                        'conflict' => true,
                        'conflict_type' => 'appointment',
                        'message' => lang('provider_has_conflicting_appointment'),
                    ]);
                    return;
                }

                $this->load->library('availability');

                $provider = $this->providers_model->find((int) $appointment['id_users_provider']);
                $service = $this->services_model->find((int) $appointment['id_services']);

                $provider_offers_service = in_array(
                    (int) $appointment['id_services'],
                    array_map('intval', $provider['services'] ?? []),
                    true,
                );

                $within_working_plan = $provider_offers_service
                    && $this->availability->is_within_working_plan(
                        $provider,
                        $appointment['start_datetime'],
                        $appointment['end_datetime'],
                    );

                if (!$within_working_plan && !$force_save) {
                    json_response([
                        'success' => false,
                        'conflict' => true,
                        'conflict_type' => 'availability',
                        'message' => lang('provider_outside_working_plan'),
                    ]);
                    return;
                }

                if ($manage_mode && !empty($appointment['id'])) {
                    $existing_for_edit = $this->appointments_model->find((int) $appointment['id']);

                    if ($this->appointments_model->is_cancelled($existing_for_edit)) {
                        throw new InvalidArgumentException('Cancelled appointments cannot be modified.');
                    }

                    $this->synchronization->remove_appointment_on_provider_change($appointment['id']);
                }

                // Jitsi integration: if enabled and meeting_link is empty, generate a Jitsi meeting link
                if (setting('jitsi_enabled') === '1' && empty($appointment['meeting_link'])) {
                    $appointment['meeting_link'] = $this->jitsi_client->generate_link();
                }

                if (!$manage_mode) {
                    $appointment['id_users_created_by'] = session('user_id');
                }

                // Zoom integration: create or update a Zoom meeting for the appointment
                if ($this->zoom_client->is_enabled()) {
                    if ($manage_mode && !empty($appointment['id']) && empty($appointment['id_zoom_meeting'])) {
                        $existing_appointment = $this->appointments_model->find($appointment['id']);

                        if (!empty($existing_appointment['id_zoom_meeting'])) {
                            $appointment['id_zoom_meeting'] = $existing_appointment['id_zoom_meeting'];
                        }
                    }

                    $zoom_provider = $this->providers_model->find($appointment['id_users_provider']);
                    $zoom_service = $this->services_model->find($appointment['id_services']);
                    $zoom_customer = $this->customers_model->find($appointment['id_users_customer']);

                    $zoom_meeting = $this->zoom_client->sync_appointment(
                        $appointment,
                        $zoom_provider,
                        $zoom_service,
                        $zoom_customer,
                    );

                    $appointment['id_zoom_meeting'] = $zoom_meeting['id'];
                    $appointment['meeting_link'] = $zoom_meeting['join_url'];

                    if (setting('zoom_store_join_url_in_location') === '1' && !empty($zoom_meeting['join_url'])) {
                        $appointment['location'] = $zoom_meeting['join_url'];
                    }
                }

                $this->appointments_model->only($appointment, $this->allowed_appointment_fields);

                $this->appointments_model->optional($appointment, $this->optional_appointment_fields);

                $appointment['id'] = $this->appointments_model->save($appointment);
            }

            if (empty($appointment['id'])) {
                throw new RuntimeException('The appointment ID is not available.');
            }

            $appointment = $this->appointments_model->find($appointment['id']);
            $provider = $this->providers_model->find($appointment['id_users_provider']);
            $customer = $this->customers_model->find($appointment['id_users_customer']);
            $service = $this->services_model->find($appointment['id_services']);

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            $this->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

            if ($notify_users) {
                $this->notifications->notify_appointment_saved(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $manage_mode,
                );
            }

            $this->webhooks_client->trigger_appointment_saved($appointment, $manage_mode);

            $this->load->library('reminders');
            $this->reminders->schedule_for_appointment($appointment);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    private function check_event_permissions(int $provider_id): void
    {
        $user_id = (int) session('user_id');
        $role_slug = session('role_slug');

        if (
            $role_slug === DB_SLUG_SECRETARY &&
            !$this->secretaries_model->is_provider_supported($user_id, $provider_id)
        ) {
            abort(403);
        }

        if ($role_slug === DB_SLUG_PROVIDER && $user_id !== $provider_id) {
            abort(403);
        }
    }

    /**
     * Prevent restricted secretaries from modifying appointments they did not create.
     */
    private function check_restricted_secretary_appointment_access(?array $appointment, bool $is_new = false): void
    {
        if ($is_new || empty($appointment)) {
            return;
        }

        if (session('role_slug') !== DB_SLUG_SECRETARY) {
            return;
        }

        if (!filter_var(setting('secretary_restricted_view'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ((int) ($appointment['id_users_created_by'] ?? 0) !== (int) session('user_id')) {
            abort(403, 'Restricted secretaries can only manage appointments they created.');
        }
    }

    /**
     * Anonymize appointments for secretaries so the calendar is free/busy only.
     *
     * When restricted view is off, all appointments are anonymized.
     * When restricted view is on, the secretary's own appointments stay visible
     * (except in the service free/busy UI, which never renders appointment cards).
     *
     * @param array $appointments Appointment list (with nested customer/service/provider when present).
     * @param int $secretary_id Current secretary user ID.
     *
     * @return array
     */
    private function apply_secretary_restricted_appointment_privacy(array $appointments, int $secretary_id): array
    {
        $restricted = filter_var(setting('secretary_restricted_view'), FILTER_VALIDATE_BOOLEAN);

        foreach ($appointments as &$appointment) {
            if ($restricted && (int) ($appointment['id_users_created_by'] ?? 0) === $secretary_id) {
                continue;
            }

            $appointment = $this->anonymize_appointment_details($appointment);
        }

        unset($appointment);

        return array_values($appointments);
    }

    /**
     * Strip customer/service identity from an appointment while preserving the busy time block.
     */
    private function anonymize_appointment_details(array $appointment): array
    {
        $provider = $appointment['provider'] ?? [
            'id' => $appointment['id_users_provider'] ?? null,
            'first_name' => '',
            'last_name' => '',
            'timezone' => setting('default_timezone', 'UTC'),
        ];

        return [
            'id' => $appointment['id'] ?? null,
            'book_datetime' => $appointment['book_datetime'] ?? null,
            'start_datetime' => $appointment['start_datetime'],
            'end_datetime' => $appointment['end_datetime'],
            'location' => null,
            'meeting_link' => null,
            'notes' => '',
            'hash' => null,
            'color' => '#879DB4',
            'status' => '',
            'is_unavailability' => false,
            'is_anonymized' => true,
            'id_users_provider' => $appointment['id_users_provider'] ?? null,
            'id_users_customer' => null,
            'id_users_created_by' => $appointment['id_users_created_by'] ?? null,
            'id_services' => null,
            'id_google_calendar' => null,
            'id_caldav_calendar' => null,
            'id_zoom_meeting' => null,
            'provider' => [
                'id' => $provider['id'] ?? null,
                'first_name' => $provider['first_name'] ?? '',
                'last_name' => $provider['last_name'] ?? '',
                'timezone' => $provider['timezone'] ?? setting('default_timezone', 'UTC'),
            ],
            'service' => [
                'id' => null,
                'name' => lang('busy'),
            ],
            'customer' => [
                'id' => null,
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone_number' => '',
                'address' => '',
                'city' => '',
                'zip_code' => '',
                'notes' => '',
            ],
        ];
    }

    /**
     * Delete appointment from the database.
     *
     * This method deletes an existing appointment from the database. Once this action is finished it cannot be undone.
     * Notification emails are send to both provider and customer and the delete action is executed to the Google
     * Calendar account of the provider, if the "google_sync" setting is enabled.
     */
    public function delete_appointment(): void
    {
        try {
            method('post');

            if (cannot('delete', 'appointments')) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('appointment_id', 'numeric');
            check('cancellation_reason', 'string|null');
            check('notify_users', 'bool|null');

            $appointment_id = request('appointment_id');
            $cancellation_reason = (string) request('cancellation_reason');
            $notify_users = filter_var(request('notify_users', true), FILTER_VALIDATE_BOOLEAN);

            if (empty($appointment_id)) {
                throw new InvalidArgumentException('No appointment id provided.');
            }

            // Store appointment data for later use in this method.
            $appointment = $this->appointments_model->find($appointment_id);

            $this->check_event_permissions((int) $appointment['id_users_provider']);
            $this->check_restricted_secretary_appointment_access($appointment);

            $provider = $this->providers_model->find($appointment['id_users_provider']);
            $customer = $this->customers_model->find($appointment['id_users_customer']);
            $service = $this->services_model->find($appointment['id_services']);

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_email' => setting('company_email'),
                'company_link' => setting('company_link'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            // Soft-cancel appointment (keep row for analytics).
            $appointment = $this->appointments_model->cancel($appointment_id, $cancellation_reason ?: null);

            if (!empty($appointment['id_zoom_meeting'])) {
                $this->zoom_client->delete_meeting($appointment['id_zoom_meeting']);
            }

            if ($notify_users) {
                $this->notifications->notify_appointment_deleted(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $cancellation_reason,
                );
            }

            $this->synchronization->sync_appointment_deleted($appointment, $provider);

            $this->webhooks_client->trigger_appointment_deleted($appointment);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Insert of update unavailability to database.
     */
    public function save_unavailability(): void
    {
        try {
            method('post');

            check('unavailability', 'array');

            // Check privileges
            $unavailability = request('unavailability');

            $required_permissions = !isset($unavailability['id'])
                ? can('add', PRIV_APPOINTMENTS)
                : can('edit', PRIV_APPOINTMENTS);

            if (!$required_permissions) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            $provider_id = (int) $unavailability['id_users_provider'];

            $this->check_event_permissions($provider_id);

            $provider = $this->providers_model->find($provider_id);

            $unavailability_id = $this->unavailabilities_model->save($unavailability);

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $this->synchronization->sync_unavailability_saved($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_SAVE, $unavailability);

            json_response([
                'success' => true,
                'warnings' => $warnings ?? [],
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete an unavailability from database.
     */
    public function delete_unavailability(): void
    {
        method('post');
        try {
            if (cannot('delete', PRIV_APPOINTMENTS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('unavailability_id', 'numeric');

            $unavailability_id = request('unavailability_id');

            $unavailability = $this->unavailabilities_model->find($unavailability_id);

            $this->check_event_permissions((int) $unavailability['id_users_provider']);

            $provider = $this->providers_model->find($unavailability['id_users_provider']);

            $this->unavailabilities_model->delete($unavailability_id);

            $this->synchronization->sync_unavailability_deleted($unavailability, $provider);

            $this->webhooks_client->trigger(WEBHOOK_UNAVAILABILITY_DELETE, $unavailability);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Insert or update working plan exceptions to database.
     */
    public function save_working_plan_exception(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_USERS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('working_plan_exception', 'array');
            check('provider_id', 'numeric');

            $working_plan_exception = request('working_plan_exception');

            $provider_id = request('provider_id');

            $exception_id = $this->providers_model->save_working_plan_exception($provider_id, $working_plan_exception);

            json_response([
                'success' => true,
                'id' => $exception_id,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete a working plan exception from database.
     */
    public function delete_working_plan_exception(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_USERS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('exception_id', 'numeric');
            check('provider_id', 'numeric');

            $exception_id = request('exception_id');

            $provider_id = request('provider_id');

            $this->load->model('working_plan_exceptions_model');
            $this->working_plan_exceptions_model->delete($exception_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get Calendar Events
     *
     * This method will return all the calendar events within a specified period.
     */
    public function get_calendar_appointments_for_table_view(): void
    {
        try {
            method('post');

            $required_permissions = can('view', PRIV_APPOINTMENTS);

            if (!$required_permissions) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('start_date', 'date');
            check('end_date', 'date');

            $start_date = request('start_date') . ' 00:00:00';

            $end_date = request('end_date') . ' 23:59:59';

            $response = [
                'appointments' => $this->appointments_model->get([
                    'start_datetime >=' => $start_date,
                    'end_datetime <=' => $end_date,
                ]),
                'unavailabilities' => $this->unavailabilities_model->get([
                    'start_datetime >=' => $start_date,
                    'end_datetime <=' => $end_date,
                ]),
            ];

            foreach ($response['appointments'] as &$appointment) {
                $appointment['provider'] = $this->providers_model->find($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->find($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->find($appointment['id_users_customer']);
            }

            unset($appointment);

            $user_id = session('user_id');

            $role_slug = session('role_slug');

            // If the current user is a provider he must only see his own appointments.
            if ($role_slug === DB_SLUG_PROVIDER) {
                foreach ($response['appointments'] as $index => $appointment) {
                    if ((int) $appointment['id_users_provider'] !== (int) $user_id) {
                        unset($response['appointments'][$index]);
                    }
                }

                $response['appointments'] = array_values($response['appointments']);

                foreach ($response['unavailabilities'] as $index => $unavailability) {
                    if ((int) $unavailability['id_users_provider'] !== (int) $user_id) {
                        unset($response['unavailabilities'][$index]);
                    }
                }

                $response['unavailabilities'] = array_values($response['unavailabilities']);
            }

            // If the current user is a secretary he must only see the appointments of his providers.
            if ($role_slug === DB_SLUG_SECRETARY) {
                $providers = $this->secretaries_model->find($user_id)['providers'];

                foreach ($response['appointments'] as $index => $appointment) {
                    if (!in_array((int) $appointment['id_users_provider'], $providers)) {
                        unset($response['appointments'][$index]);
                    }
                }

                $response['appointments'] = array_values($response['appointments']);

                foreach ($response['unavailabilities'] as $index => $unavailability) {
                    if (!in_array((int) $unavailability['id_users_provider'], $providers)) {
                        unset($response['unavailabilities'][$index]);
                    }
                }

                $response['unavailabilities'] = array_values($response['unavailabilities']);

                // Restricted secretaries still see other appointments as busy blocks without names/types.
                $response['appointments'] = $this->apply_secretary_restricted_appointment_privacy(
                    $response['appointments'],
                    (int) $user_id,
                );
            }

            foreach ($response['unavailabilities'] as &$unavailability) {
                $unavailability['provider'] = $this->providers_model->find($unavailability['id_users_provider']);
            }

            unset($unavailability);

            // Add blocked periods to the response.
            $start_date = request('start_date');
            $end_date = request('end_date');
            $response['blocked_periods'] = $this->blocked_periods_model->get_for_period($start_date, $end_date);

            json_response($response);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get the registered appointments for the given date period and record.
     *
     * This method returns the database appointments and unavailability periods for the user selected date period and
     * record type (provider or service).
     */
    public function get_calendar_appointments(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                throw new RuntimeException('You do not have the required permissions for this task.');
            }

            check('record_id', 'string|numeric|null');
            check('filter_type', 'string|null');
            check('start_date', 'date');
            check('end_date', 'date');

            $record_id = request('record_id');

            $is_all = request('record_id') === FILTER_TYPE_ALL;

            $filter_type = request('filter_type');

            if (!$filter_type && !$is_all) {
                json_response([
                    'appointments' => [],
                    'unavailabilities' => [],
                ]);

                return;
            }

            // Validate filter_type to prevent SQL injection via column name
            $allowed_filter_types = [FILTER_TYPE_PROVIDER, FILTER_TYPE_SERVICE, FILTER_TYPE_ALL];
            if ($filter_type && !in_array($filter_type, $allowed_filter_types, true)) {
                throw new InvalidArgumentException('Invalid filter type provided.');
            }

            // Determine which column to filter by
            if ($filter_type == FILTER_TYPE_PROVIDER) {
                $where_id = 'id_users_provider';
            } elseif ($filter_type === FILTER_TYPE_SERVICE) {
                $where_id = 'id_services';
            } else {
                $where_id = 'id_users_provider'; // Default for FILTER_TYPE_ALL
            }

            // Validate record_id is numeric when not "all"
            if (!$is_all && !is_numeric($record_id)) {
                throw new InvalidArgumentException('Invalid record ID provided.');
            }

            // Get appointments using query builder for safety
            $start_date = request('start_date');
            $end_date = date('Y-m-d', strtotime(request('end_date') . ' +1 day'));

            // Service filter: load all appointments of providers offering the service
            // (any service) so busy time and availability overlay are correct.
            $service_provider_ids = [];

            if ($filter_type === FILTER_TYPE_SERVICE && !$is_all) {
                $service_provider_ids = $this->db
                    ->select('id_users')
                    ->from('services_providers')
                    ->where('id_services', $record_id)
                    ->get()
                    ->result_array();

                $service_provider_ids = array_map('intval', array_column($service_provider_ids, 'id_users'));
            }

            // Build query using CodeIgniter's query builder for SQL injection protection
            $this->db->select('*');
            $this->db->from('appointments');

            if ($filter_type === FILTER_TYPE_SERVICE && !$is_all) {
                if (empty($service_provider_ids)) {
                    $this->db->where('1 = 0', null, false);
                } else {
                    $this->db->where_in('id_users_provider', $service_provider_ids);
                }
            } elseif (!$is_all) {
                $this->db->where($where_id, $record_id);
            }

            $this->db->group_start();
            $this->db->group_start();
            $this->db->where('start_datetime >', $start_date);
            $this->db->where('start_datetime <', $end_date);
            $this->db->group_end();
            $this->db->or_group_start();
            $this->db->where('end_datetime >', $start_date);
            $this->db->where('end_datetime <', $end_date);
            $this->db->group_end();
            $this->db->or_group_start();
            $this->db->where('start_datetime <=', $start_date);
            $this->db->where('end_datetime >=', $end_date);
            $this->db->group_end();
            $this->db->group_end();

            $this->db->where('is_unavailability', 0);
            $this->appointments_model->exclude_cancelled_appointments('');

            $response['appointments'] = $this->db->get()->result_array();

            foreach ($response['appointments'] as &$appointment) {
                $appointment['provider'] = $this->providers_model->find($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->find($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->find($appointment['id_users_customer']);
            }

            unset($appointment);

            // Unavailabilities for provider/all/service (providers offering the service).
            $response['unavailabilities'] = [];

            if ($filter_type == FILTER_TYPE_PROVIDER || $filter_type === FILTER_TYPE_SERVICE || $is_all) {
                $this->db->select('*');
                $this->db->from('appointments');

                if ($filter_type === FILTER_TYPE_SERVICE && !$is_all) {
                    if (empty($service_provider_ids)) {
                        $this->db->where('1 = 0', null, false);
                    } else {
                        $this->db->where_in('id_users_provider', $service_provider_ids);
                    }
                } elseif (!$is_all) {
                    $this->db->where($where_id, $record_id);
                }

                $this->db->group_start();
                $this->db->group_start();
                $this->db->where('start_datetime >', $start_date);
                $this->db->where('start_datetime <', $end_date);
                $this->db->group_end();
                $this->db->or_group_start();
                $this->db->where('end_datetime >', $start_date);
                $this->db->where('end_datetime <', $end_date);
                $this->db->group_end();
                $this->db->or_group_start();
                $this->db->where('start_datetime <=', $start_date);
                $this->db->where('end_datetime >=', $end_date);
                $this->db->group_end();
                $this->db->group_end();

                $this->db->where('is_unavailability', 1);

                $response['unavailabilities'] = $this->db->get()->result_array();
            }

            $user_id = session('user_id');

            $role_slug = session('role_slug');

            // If the current user is a provider he must only see his own appointments.
            if ($role_slug === DB_SLUG_PROVIDER) {
                foreach ($response['appointments'] as $index => $appointment) {
                    if ((int) $appointment['id_users_provider'] !== (int) $user_id) {
                        unset($response['appointments'][$index]);
                    }
                }

                $response['appointments'] = array_values($response['appointments']);

                foreach ($response['unavailabilities'] as $index => $unavailability) {
                    if ((int) $unavailability['id_users_provider'] !== (int) $user_id) {
                        unset($response['unavailabilities'][$index]);
                    }
                }

                unset($unavailability);

                $response['unavailabilities'] = array_values($response['unavailabilities']);
            }

            // If the current user is a secretary he must only see the appointments of his providers.
            if ($role_slug === DB_SLUG_SECRETARY) {
                $providers = $this->secretaries_model->find($user_id)['providers'];

                foreach ($response['appointments'] as $index => $appointment) {
                    if (!in_array((int) $appointment['id_users_provider'], $providers)) {
                        unset($response['appointments'][$index]);
                    }
                }

                $response['appointments'] = array_values($response['appointments']);

                foreach ($response['unavailabilities'] as $index => $unavailability) {
                    if (!in_array((int) $unavailability['id_users_provider'], $providers)) {
                        unset($response['unavailabilities'][$index]);
                    }
                }

                $response['unavailabilities'] = array_values($response['unavailabilities']);

                // Restricted secretaries still see other appointments as busy blocks without names/types.
                $response['appointments'] = $this->apply_secretary_restricted_appointment_privacy(
                    $response['appointments'],
                    (int) $user_id,
                );
            }

            foreach ($response['unavailabilities'] as &$unavailability) {
                $unavailability['provider'] = $this->providers_model->find($unavailability['id_users_provider']);
            }

            unset($unavailability);

            // Add blocked periods to the response.
            $start_date = request('start_date');
            $end_date = request('end_date');
            $response['blocked_periods'] = $this->blocked_periods_model->get_for_period($start_date, $end_date);

            json_response($response);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
