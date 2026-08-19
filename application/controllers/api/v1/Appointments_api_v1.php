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
 * Appointments API v1 controller.
 *
 * @package Controllers
 */
class Appointments_api_v1 extends EA_Controller
{
    /**
     * Appointments_api_v1 constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');

        $this->load->library('api');
        $this->load->library('webhooks_client');
        $this->load->library('synchronization');
        $this->load->library('notifications');

        $this->api->auth();

        $this->api->model('appointments_model');
    }

    /**
     * Get an appointment collection.
     */
    public function index(): void
    {
        try {
            $keyword = $this->api->request_keyword();

            $limit = $this->api->request_limit();

            $offset = $this->api->request_offset();

            $order_by = $this->api->request_order_by();

            $fields = $this->api->request_fields();

            $with = $this->api->request_with();

            $where = null;

            // Date query param.

            $date = request('date');

            if (!empty($date)) {
                $where['DATE(start_datetime)'] = (new DateTime($date))->format('Y-m-d');
            }

            // From query param.

            $from = request('from');

            if (!empty($from)) {
                $where['DATE(start_datetime) >='] = (new DateTime($from))->format('Y-m-d');
            }

            // Till query param.

            $till = request('till');

            if (!empty($till)) {
                $where['DATE(end_datetime) <='] = (new DateTime($till))->format('Y-m-d');
            }

            // Service ID query param.

            $service_id = request('serviceId');

            if (!empty($service_id)) {
                $where['id_services'] = $service_id;
            }

            // Provider ID query param.

            $provider_id = request('providerId');

            if (!empty($provider_id)) {
                $where['id_users_provider'] = $provider_id;
            }

            // Customer ID query param.

            $customer_id = request('customerId');

            if (!empty($customer_id)) {
                $where['id_users_customer'] = $customer_id;
            }

            // Created-by / status filters (parity with appointment statistics).

            $created_by_id = request('createdById');

            if (!empty($created_by_id)) {
                $where['id_users_created_by'] = (int) $created_by_id;
            }

            $status = trim((string) request('status', ''));

            if ($status !== '') {
                $where['status'] = $status;
            }

            foreach (
                [
                    'utmSource' => 'utm_source',
                    'utmMedium' => 'utm_medium',
                    'utmCampaign' => 'utm_campaign',
                    'utmTerm' => 'utm_term',
                    'utmContent' => 'utm_content',
                ]
                as $param => $column
            ) {
                $value = trim((string) request($param, ''));

                if ($value !== '') {
                    $where[$column] = $value;
                }
            }

            // Secretary ID query param: limit to that secretary's providers
            // (and optionally to appointments they created when restricted view is on).

            $secretary_id = request('secretaryId');
            $secretary_provider_ids = null;

            if (!empty($secretary_id)) {
                $this->load->model('secretaries_model');

                // Prefer the lightweight provider lookup so missing settings records do not break the endpoint.
                if (method_exists($this->secretaries_model, 'get_provider_ids')) {
                    $secretary_provider_ids = array_map(
                        'intval',
                        $this->secretaries_model->get_provider_ids((int) $secretary_id),
                    );
                } else {
                    $secretary = $this->secretaries_model->find((int) $secretary_id);
                    $secretary_provider_ids = array_map('intval', $secretary['providers'] ?? []);
                }

                if (empty($secretary_provider_ids)) {
                    json_response([]);
                    return;
                }

                if (filter_var(setting('secretary_restricted_view'), FILTER_VALIDATE_BOOLEAN)) {
                    $where['id_users_created_by'] = (int) $secretary_id;
                }
            }

            $include_cancelled = filter_var(request('includeCancelled'), FILTER_VALIDATE_BOOLEAN);

            // Filtering for a cancelled status must include soft-cancelled rows.
            if ($status !== '' && in_array(strtolower($status), ['cancelled', 'canceled'], true)) {
                $include_cancelled = true;
            }

            $appointments = empty($keyword)
                ? $this->appointments_model->get($where, $limit, $offset, $order_by, $include_cancelled)
                : $this->appointments_model->search($keyword, $limit, $offset, $order_by, $include_cancelled);

            if ($secretary_provider_ids !== null) {
                $appointments = array_values(
                    array_map(static function (array $appointment) use ($secretary_provider_ids, $secretary_id) {
                        if (!in_array((int) $appointment['id_users_provider'], $secretary_provider_ids, true)) {
                            return null;
                        }

                        if ((int) ($appointment['id_users_created_by'] ?? 0) !== (int) $secretary_id) {
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
                            ];
                        }

                        return $appointment;
                    }, $appointments),
                );

                $appointments = array_values(array_filter($appointments));
            }

            foreach ($appointments as &$appointment) {
                $this->appointments_model->api_encode($appointment);

                $this->aggregates($appointment);

                if (!empty($fields)) {
                    $this->appointments_model->only($appointment, $fields);
                }

                if (!empty($with)) {
                    $this->appointments_model->load($appointment, $with);
                }
            }

            json_response($appointments);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Load the relations of the current appointment if the "aggregates" query parameter is present.
     *
     * This is a compatibility addition to the appointment resource which was the only one to support it.
     *
     * Use the "attach" query parameter instead as this one will be removed.
     *
     * @param array $appointment Appointment data.
     *
     * @deprecated Since 1.5
     */
    private function aggregates(array &$appointment): void
    {
        $aggregates = request('aggregates') !== null;

        if ($aggregates) {
            $appointment['service'] = $this->services_model->find(
                $appointment['id_services'] ?? ($appointment['serviceId'] ?? null),
            );
            $appointment['provider'] = $this->providers_model->find(
                $appointment['id_users_provider'] ?? ($appointment['providerId'] ?? null),
            );
            $appointment['customer'] = $this->customers_model->find(
                $appointment['id_users_customer'] ?? ($appointment['customerId'] ?? null),
            );
            $this->services_model->api_encode($appointment['service']);
            $this->providers_model->api_encode($appointment['provider']);
            $this->customers_model->api_encode($appointment['customer']);
        }
    }

    /**
     * Get a single appointment.
     *
     * @param int|null $id Appointment ID.
     */
    public function show(?int $id = null): void
    {
        try {
            $occurrences = $this->appointments_model->get(['id' => $id]);

            if (empty($occurrences)) {
                response('', 404);

                return;
            }

            $fields = $this->api->request_fields();

            $with = $this->api->request_with();

            $appointment = $this->appointments_model->find($id);

            $this->appointments_model->api_encode($appointment);

            if (!empty($fields)) {
                $this->appointments_model->only($appointment, $fields);
            }

            if (!empty($with)) {
                $this->appointments_model->load($appointment, $with);
            }

            json_response($appointment);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Store a new appointment.
     */
    public function store(): void
    {
        try {
            $appointment = request();

            $this->appointments_model->api_decode($appointment);

            if (array_key_exists('id', $appointment)) {
                unset($appointment['id']);
            }

            if (!array_key_exists('end_datetime', $appointment)) {
                $appointment['end_datetime'] = $this->appointments_model->calculate_end_datetime($appointment);
            }

            $appointment_id = $this->appointments_model->save($appointment);

            $created_appointment = $this->appointments_model->find($appointment_id);

            $this->notify_and_sync_appointment($created_appointment);

            $this->appointments_model->api_encode($created_appointment);

            json_response($created_appointment, 201);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Send the required notifications and trigger syncing after saving an appointment.
     *
     * @param array $appointment Appointment data.
     * @param string $action Performed action ("store" or "update").
     */
    private function notify_and_sync_appointment(array $appointment, string $action = 'store'): void
    {
        $manage_mode = $action === 'update';

        $service = $this->services_model->find($appointment['id_services']);

        $provider = $this->providers_model->find($appointment['id_users_provider']);

        $customer = $this->customers_model->find($appointment['id_users_customer']);

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

        $this->synchronization->sync_appointment_saved($appointment, $service, $provider, $customer, $settings);

        $this->notifications->notify_appointment_saved(
            $appointment,
            $service,
            $provider,
            $customer,
            $settings,
            $manage_mode,
        );

        $this->webhooks_client->trigger_appointment_saved($appointment, $manage_mode);

        $this->load->library('reminders');
        $this->reminders->schedule_for_appointment($appointment);
    }

    /**
     * Update an appointment.
     *
     * @param int $id Appointment ID.
     */
    public function update(int $id): void
    {
        try {
            $occurrences = $this->appointments_model->get(['id' => $id]);

            if (empty($occurrences)) {
                response('', 404);

                return;
            }

            $original_appointment = $occurrences[0];

            $appointment = request();

            $this->appointments_model->api_decode($appointment, $original_appointment);

            $appointment_id = $this->appointments_model->save($appointment);

            $updated_appointment = $this->appointments_model->find($appointment_id);

            $this->notify_and_sync_appointment($updated_appointment, 'update');

            $this->appointments_model->api_encode($updated_appointment);

            json_response($updated_appointment);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete an appointment.
     *
     * @param int $id Appointment ID.
     */
    public function destroy(int $id): void
    {
        try {
            $occurrences = $this->appointments_model->get(['id' => $id]);

            if (empty($occurrences)) {
                response('', 404);

                return;
            }

            $deleted_appointment = $occurrences[0];

            $service = $this->services_model->find($deleted_appointment['id_services']);

            $provider = $this->providers_model->find($deleted_appointment['id_users_provider']);

            $customer = $this->customers_model->find($deleted_appointment['id_users_customer']);

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

            $deleted_appointment = $this->appointments_model->cancel($id);

            $this->synchronization->sync_appointment_deleted($deleted_appointment, $provider);

            $this->notifications->notify_appointment_deleted(
                $deleted_appointment,
                $service,
                $provider,
                $customer,
                $settings,
            );

            $this->webhooks_client->trigger_appointment_deleted($deleted_appointment);

            response('', 204);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
