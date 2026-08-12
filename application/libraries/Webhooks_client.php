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
     * @param array $appointment Appointment database row.
     * @param bool $is_update Whether the appointment was updated (false = created).
     */
    public function trigger_appointment_saved(array $appointment, bool $is_update = false): void
    {
        $payload = $this->prepare_appointment_payload($appointment);

        $this->trigger(
            $is_update ? WEBHOOK_APPOINTMENT_UPDATE : WEBHOOK_APPOINTMENT_CREATE,
            $payload,
        );

        // Keep the legacy save action for existing integrations.
        $this->trigger(WEBHOOK_APPOINTMENT_SAVE, $payload);
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
