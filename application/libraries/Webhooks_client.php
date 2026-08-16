<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

use GuzzleHttp\Client;

/**
 * Webhooks client library.
 *
 * Handles the webhook HTTP related functionality.
 *
 * @package Libraries
 */
class Webhooks_client
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Webhook client constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->model('webhooks_model');
    }

    /**
     * Trigger the registered webhooks for the provided action.
     *
     * @param string $action Webhook action.
     * @param array $payload Payload data.
     *
     * @return void|null
     */
    public function trigger(string $action, array $payload)
    {
        $webhooks = $this->CI->webhooks_model->get();

        foreach ($webhooks as $webhook) {
            $actions = array_filter(array_map('trim', explode(',', (string) $webhook['actions'])));

            if (in_array($action, $actions, true)) {
                $this->call($webhook, $action, $payload);
            }
        }
    }

    /**
     * Trigger create/update appointment webhooks (and legacy save).
     *
     * Each matching webhook is called at most once:
     * - Prefer appointment_create / appointment_update when selected
     * - Fall back to legacy appointment_save only when the specific action is not selected
     *
     * This prevents double delivery when both "create" and "save" are enabled
     * on the same webhook (common n8n misconfiguration that caused 2–4 emails).
     *
     * @param array $appointment Appointment database row.
     * @param bool $is_update Whether the appointment was updated (false = created).
     */
    public function trigger_appointment_saved(array $appointment, bool $is_update = false): void
    {
        $payload = $this->prepare_appointment_payload($appointment);

        $webhooks = $this->CI->webhooks_model->get();

        foreach ($webhooks as $webhook) {
            $actions = array_filter(array_map('trim', explode(',', (string) ($webhook['actions'] ?? ''))));
            $action = $this->resolve_appointment_saved_action($actions, $is_update);

            if ($action !== null) {
                $this->call($webhook, $action, $payload);
            }
        }
    }

    /**
     * Decide which appointment-saved action to emit for one webhook.
     *
     * @param array $actions Actions configured on the webhook.
     * @param bool $is_update Whether this save is an update.
     */
    public function resolve_appointment_saved_action(array $actions, bool $is_update = false): ?string
    {
        $specific_action = $is_update ? WEBHOOK_APPOINTMENT_UPDATE : WEBHOOK_APPOINTMENT_CREATE;

        if (in_array($specific_action, $actions, true)) {
            return $specific_action;
        }

        if (in_array(WEBHOOK_APPOINTMENT_SAVE, $actions, true)) {
            return WEBHOOK_APPOINTMENT_SAVE;
        }

        return null;
    }

    /**
     * Trigger appointment delete webhooks.
     *
     * @param array $appointment Appointment database row.
     */
    public function trigger_appointment_deleted(array $appointment): void
    {
        $this->trigger(WEBHOOK_APPOINTMENT_DELETE, $this->prepare_appointment_payload($appointment));
    }

    /**
     * Trigger appointment reminder webhooks.
     */
    public function trigger_appointment_reminder(
        array $appointment,
        array $customer,
        array $provider,
        array $service,
        array $reminder,
    ): void {
        $this->trigger(WEBHOOK_APPOINTMENT_REMINDER, [
            'appointment' => $this->prepare_appointment_payload($appointment),
            'customer' => [
                'id' => $customer['id'] ?? null,
                'first_name' => $customer['first_name'] ?? null,
                'last_name' => $customer['last_name'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone_number' => $customer['phone_number'] ?? null,
            ],
            'provider' => [
                'id' => $provider['id'] ?? null,
                'first_name' => $provider['first_name'] ?? null,
                'last_name' => $provider['last_name'] ?? null,
                'email' => $provider['email'] ?? null,
            ],
            'service' => [
                'id' => $service['id'] ?? null,
                'name' => $service['name'] ?? null,
                'duration' => $service['duration'] ?? null,
            ],
            'reminder' => [
                'key' => $reminder['id'] ?? null,
                'offset' => $reminder['offset'] ?? null,
                'unit' => $reminder['unit'] ?? null,
            ],
        ]);
    }

    /**
     * Normalize appointment webhook payload ownership fields.
     */
    public function prepare_appointment_payload(array $appointment): array
    {
        if (array_key_exists('id_users_created_by', $appointment)) {
            $appointment['id_users_created_by'] = $appointment['id_users_created_by'] !== null
                ? (int) $appointment['id_users_created_by']
                : null;
        }

        return $appointment;
    }

    /**
     * Call the provided webhook.
     *
     * @param array $webhook
     * @param string $action
     * @param array $payload
     */
    private function call(array $webhook, string $action, array $payload): void
    {
        try {
            $client = new Client();

            $headers = [];

            if (!empty($webhook['secret_header']) && !empty($webhook['secret_token'])) {
                $headers[$webhook['secret_header']] = $webhook['secret_token'];
            }

            $client->post($webhook['url'], [
                'verify' => $webhook['is_ssl_verified'],
                'headers' => $headers,
                'json' => [
                    'action' => $action,
                    'payload' => $payload,
                ],
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Webhooks Client - The webhook (' .
                    ($webhook['id'] ?? null) .
                    ') request received an unexpected exception: ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }
}
