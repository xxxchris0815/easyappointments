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
     * All appointment fields that can meaningfully change and are included in update diffs.
     *
     * Identity / audit timestamps are excluded on purpose.
     */
    private const APPOINTMENT_DIFF_FIELDS = [
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
        'id_google_calendar',
        'id_caldav_calendar',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * Integer-like appointment fields normalized in diffs.
     */
    private const APPOINTMENT_INT_FIELDS = [
        'id_zoom_meeting',
        'id_users_provider',
        'id_users_customer',
        'id_users_created_by',
        'id_services',
    ];

    /**
     * Identity fields always kept on appointment_update payloads.
     */
    private const APPOINTMENT_UPDATE_IDENTITY_FIELDS = [
        'id',
        'hash',
        'modify_link',
        'cancel_link',
    ];

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
     * Create payloads contain the full appointment row plus modify/cancel links.
     * Update payloads contain identity + links + only changed fields (with from/to).
     *
     * @param array $appointment Appointment database row (after save).
     * @param bool $is_update Whether the appointment was updated (false = created).
     * @param array|null $previous_appointment Appointment row before the update (required for diffs).
     */
    public function trigger_appointment_saved(
        array $appointment,
        bool $is_update = false,
        ?array $previous_appointment = null,
    ): void {
        // Re-read after external sync (Google/CalDAV/Zoom) so calendar/meeting IDs are current.
        if (!empty($appointment['id']) && isset($this->CI) && isset($this->CI->appointments_model)) {
            try {
                $fresh = $this->CI->appointments_model->find((int) $appointment['id']);
                if (!empty($fresh)) {
                    $appointment = $fresh;
                }
            } catch (Throwable $e) {
                // Keep the in-memory appointment if reload fails.
            }
        }

        $payload = $is_update
            ? $this->prepare_appointment_update_payload($appointment, $previous_appointment)
            : $this->prepare_appointment_payload($appointment);

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
     * Normalize appointment webhook payload and append customer manage links.
     *
     * The appointment `hash` is generated once on create and never changes, so
     * modify/cancel URLs stay stable for the lifetime of the appointment:
     * - modify_link: GET  {base}/booking/reschedule/{hash}
     * - cancel_link: POST {base}/booking_cancellation/of/{hash} (requires cancellation_reason)
     */
    public function prepare_appointment_payload(array $appointment): array
    {
        if (array_key_exists('id_users_created_by', $appointment)) {
            $appointment['id_users_created_by'] = $appointment['id_users_created_by'] !== null
                ? (int) $appointment['id_users_created_by']
                : null;
        }

        return array_merge($appointment, $this->build_appointment_links($appointment));
    }

    /**
     * Build an update webhook payload with identity + only changed fields.
     *
     * Changed fields are exposed twice for n8n convenience:
     * - `changes.<field> = { from, to }`
     * - top-level `<field> = to` (new value only)
     *
     * @param array $appointment Appointment after save.
     * @param array|null $previous_appointment Appointment before save.
     */
    public function prepare_appointment_update_payload(array $appointment, ?array $previous_appointment = null): array
    {
        $current = $this->prepare_appointment_payload($appointment);

        $payload = [];

        foreach (self::APPOINTMENT_UPDATE_IDENTITY_FIELDS as $field) {
            if (array_key_exists($field, $current)) {
                $payload[$field] = $current[$field];
            }
        }

        $changes = $this->diff_appointment_fields($previous_appointment ?? [], $appointment);
        $payload['changes'] = $changes;
        $payload['changed_fields'] = array_keys($changes);

        foreach ($changes as $field => $change) {
            $payload[$field] = $change['to'];
        }

        return $payload;
    }

    /**
     * Diff appointment rows and return only changed trackable fields as from/to pairs.
     *
     * Covers zoom/meeting links, provider/customer/service, dates, notes, status,
     * location, color, calendar IDs, UTMs, etc.
     *
     * @param array $previous Previous appointment row.
     * @param array $current Current appointment row.
     */
    public function diff_appointment_fields(array $previous, array $current): array
    {
        $changes = [];

        foreach (self::APPOINTMENT_DIFF_FIELDS as $key) {
            $from = array_key_exists($key, $previous) ? $previous[$key] : null;
            $to = array_key_exists($key, $current) ? $current[$key] : null;

            if (in_array($key, self::APPOINTMENT_INT_FIELDS, true)) {
                $from = $this->normalize_appointment_int($from);
                $to = $this->normalize_appointment_int($to);
            }

            if ($key === 'is_unavailability') {
                $from = $this->normalize_appointment_bool($from);
                $to = $this->normalize_appointment_bool($to);
            }

            if ($this->appointment_values_equal($from, $to)) {
                continue;
            }

            $changes[$key] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        return $changes;
    }

    /**
     * Trackable appointment fields used for update diffs.
     */
    public function get_appointment_diff_fields(): array
    {
        return self::APPOINTMENT_DIFF_FIELDS;
    }

    /**
     * Build stable customer-facing appointment links from the hash.
     */
    public function build_appointment_links(array $appointment): array
    {
        $hash = $appointment['hash'] ?? null;

        if ($hash === null || $hash === '') {
            return [
                'modify_link' => null,
                'cancel_link' => null,
            ];
        }

        $modify_path = 'booking/reschedule/' . $hash;
        $cancel_path = 'booking_cancellation/of/' . $hash;

        if (function_exists('site_url')) {
            return [
                'modify_link' => site_url($modify_path),
                'cancel_link' => site_url($cancel_path),
            ];
        }

        return [
            'modify_link' => $modify_path,
            'cancel_link' => $cancel_path,
        ];
    }

    /**
     * Compare appointment field values with light normalization.
     */
    private function appointment_values_equal(mixed $left, mixed $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if ($this->is_empty_appointment_value($left) && $this->is_empty_appointment_value($right)) {
            return true;
        }

        if (is_bool($left) || is_bool($right)) {
            return (bool) $left === (bool) $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (string) (0 + $left) === (string) (0 + $right);
        }

        return (string) $left === (string) $right;
    }

    private function is_empty_appointment_value(mixed $value): bool
    {
        return $value === null || $value === '' || $value === false;
    }

    private function normalize_appointment_int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalize_appointment_bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
