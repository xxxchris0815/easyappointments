<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Zoom Server-to-Server OAuth client.
 * ---------------------------------------------------------------------------- */

/**
 * Zoom Client Library
 *
 * Creates and updates Zoom meetings using Server-to-Server OAuth.
 *
 * Zoom failures must never abort appointment save/delete — booking always wins.
 *
 * @package Libraries
 */
class Zoom_client
{
    protected EA_Controller|CI_Controller $CI;

    protected ?string $access_token = null;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Whether Zoom meeting generation is enabled and configured.
     */
    public function is_enabled(): bool
    {
        return filter_var(setting('zoom_enabled'), FILTER_VALIDATE_BOOLEAN) &&
            !empty(setting('zoom_account_id')) &&
            !empty(setting('zoom_client_id')) &&
            !empty(setting('zoom_client_secret'));
    }

    /**
     * Create or update a Zoom meeting for an appointment.
     *
     * On any Zoom/API failure this returns the previous meeting values (or nulls)
     * and logs the error — callers must continue saving the appointment.
     *
     * @return array{id:?string,join_url:?string,success:bool}
     */
    public function sync_appointment(array $appointment, array $provider, array $service, array $customer): array
    {
        $fallback = [
            'id' => $appointment['id_zoom_meeting'] ?? null,
            'join_url' => $appointment['meeting_link'] ?? null,
            'success' => false,
        ];

        try {
            if (!$this->is_enabled()) {
                return $fallback;
            }

            $zoom_user = $provider['settings']['zoom_email'] ?? $provider['email'] ?? null;

            if (empty($zoom_user)) {
                log_message('error', 'Zoom sync skipped: provider Zoom email is not configured.');

                return $fallback;
            }

            $token = $this->get_access_token();

            // Appointment datetimes are stored as naive local values in the provider timezone
            // (same convention as Google_sync). Send local start_time + timezone to Zoom —
            // do NOT treat the stored value as UTC (that shifted meetings by the TZ offset).
            $timezone_name = $provider['timezone'] ?? setting('default_timezone', 'UTC') ?: 'UTC';

            try {
                $timezone = new DateTimeZone($timezone_name);
            } catch (Throwable) {
                $timezone_name = 'UTC';
                $timezone = new DateTimeZone('UTC');
            }

            $start = new DateTime((string) $appointment['start_datetime'], $timezone);
            $end = new DateTime((string) $appointment['end_datetime'], $timezone);

            $payload = [
                'topic' => $this->build_meeting_topic($appointment, $service, $provider, $customer),
                'type' => 2,
                'start_time' => $start->format('Y-m-d\TH:i:s'),
                'duration' => max(1, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60)),
                'timezone' => $timezone_name,
                'agenda' => $appointment['notes'] ?? '',
                'settings' => [
                    'join_before_host' => true,
                    'waiting_room' => false,
                ],
            ];

            if (!empty($appointment['id_zoom_meeting'])) {
                $this->request(
                    'PATCH',
                    'https://api.zoom.us/v2/meetings/' . rawurlencode((string) $appointment['id_zoom_meeting']),
                    $payload,
                    $token,
                );

                $meeting = $this->request(
                    'GET',
                    'https://api.zoom.us/v2/meetings/' . rawurlencode((string) $appointment['id_zoom_meeting']),
                    null,
                    $token,
                );

                return [
                    'id' => (string) ($meeting['id'] ?? $appointment['id_zoom_meeting']),
                    'join_url' => $meeting['join_url'] ?? ($appointment['meeting_link'] ?? null),
                    'success' => true,
                ];
            }

            $meeting = $this->request(
                'POST',
                'https://api.zoom.us/v2/users/' . rawurlencode($zoom_user) . '/meetings',
                $payload,
                $token,
            );

            return [
                'id' => isset($meeting['id']) ? (string) $meeting['id'] : null,
                'join_url' => $meeting['join_url'] ?? null,
                'success' => !empty($meeting['id']) || !empty($meeting['join_url']),
            ];
        } catch (Throwable $e) {
            log_message(
                'error',
                'Zoom sync failed (appointment booking continues): ' . $e->getMessage(),
            );

            return $fallback;
        }
    }

    /**
     * Default meeting topic template when the setting is empty.
     */
    public const DEFAULT_TOPIC_TEMPLATE = '{service} - {customer_first_name} - {customer_email}';

    /**
     * Placeholder tokens supported in the Zoom meeting topic template.
     *
     * @return list<string>
     */
    public function available_topic_placeholders(): array
    {
        return [
            'service',
            'customer_first_name',
            'customer_last_name',
            'customer_name',
            'customer_email',
            'customer_phone',
            'provider',
            'provider_first_name',
            'provider_last_name',
            'date',
            'time',
            'end_time',
            'datetime',
            'start_datetime',
            'end_datetime',
            'appointment_id',
            'location',
        ];
    }

    /**
     * Build the Zoom meeting topic from the configured template.
     *
     * Template example: `{service} - {customer_first_name} - {customer_email}`
     * Empty placeholders and dangling " - " separators are cleaned up.
     * Zoom allows up to 200 characters for topic; longer values are truncated.
     */
    public function build_meeting_topic(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        ?string $template = null,
    ): string {
        $template = trim((string) ($template ?? setting('zoom_meeting_topic_template', self::DEFAULT_TOPIC_TEMPLATE)));

        if ($template === '') {
            $template = self::DEFAULT_TOPIC_TEMPLATE;
        }

        $start = (string) ($appointment['start_datetime'] ?? '');
        $end = (string) ($appointment['end_datetime'] ?? '');
        $customer_first = trim((string) ($customer['first_name'] ?? ''));
        $customer_last = trim((string) ($customer['last_name'] ?? ''));
        $provider_first = trim((string) ($provider['first_name'] ?? ''));
        $provider_last = trim((string) ($provider['last_name'] ?? ''));

        $values = [
            'service' => trim((string) ($service['name'] ?? '')),
            'customer_first_name' => $customer_first,
            'customer_last_name' => $customer_last,
            'customer_name' => trim($customer_first . ' ' . $customer_last),
            'customer_email' => trim((string) ($customer['email'] ?? '')),
            'customer_phone' => trim((string) ($customer['phone_number'] ?? '')),
            'provider' => trim($provider_first . ' ' . $provider_last),
            'provider_first_name' => $provider_first,
            'provider_last_name' => $provider_last,
            'date' => $start !== '' && function_exists('format_date') ? (string) format_date($start) : $start,
            'time' => $start !== '' && function_exists('format_time') ? (string) format_time($start) : $start,
            'end_time' => $end !== '' && function_exists('format_time') ? (string) format_time($end) : $end,
            'datetime' => $start !== '' && function_exists('format_date_time')
                ? (string) format_date_time($start)
                : $start,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'appointment_id' => (string) ($appointment['id'] ?? ''),
            'location' => trim((string) ($appointment['location'] ?? '')),
        ];

        $topic = preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($values): string {
                $key = strtolower($matches[1]);

                return array_key_exists($key, $values) ? (string) $values[$key] : '';
            },
            $template,
        );

        // Collapse empty segments left by missing placeholders ("A -  - B" → "A - B").
        $topic = preg_replace('/(\s*-\s*){2,}/', ' - ', (string) $topic);
        $topic = preg_replace('/^\s*-\s*|\s*-\s*$/', '', (string) $topic);
        $topic = trim((string) $topic);

        if ($topic === '') {
            $topic = !empty($values['service']) ? $values['service'] : 'Appointment';
        }

        // Zoom meeting topic max length is 200 characters.
        if (mb_strlen($topic) > 200) {
            $topic = rtrim(mb_substr($topic, 0, 197)) . '...';
        }

        return $topic;
    }

    /**
     * Delete a Zoom meeting.
     *
     * Failures are logged only — appointment cancel/delete must continue.
     */
    public function delete_meeting(?string $meeting_id): void
    {
        try {
            if (!$this->is_enabled() || empty($meeting_id)) {
                return;
            }

            $this->request(
                'DELETE',
                'https://api.zoom.us/v2/meetings/' . rawurlencode($meeting_id),
                null,
                $this->get_access_token(),
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                'Zoom delete failed (appointment operation continues): ' . $e->getMessage(),
            );
        }
    }

    /**
     * Fetch a Server-to-Server OAuth access token.
     */
    protected function get_access_token(): string
    {
        if (!empty($this->access_token)) {
            return $this->access_token;
        }

        $account_id = setting('zoom_account_id');
        $client_id = setting('zoom_client_id');
        $client_secret = setting('zoom_client_secret');

        $ch = curl_init('https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . rawurlencode($account_id));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
            ],
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Zoom token request failed: ' . $error);
        }

        $data = json_decode($response, true);

        if ($status >= 400 || empty($data['access_token'])) {
            throw new RuntimeException('Zoom token request failed with status ' . $status . ': ' . $response);
        }

        $this->access_token = $data['access_token'];

        return $this->access_token;
    }

    /**
     * Perform an authenticated Zoom API request.
     */
    protected function request(string $method, string $url, ?array $payload, string $token): array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Zoom API request failed: ' . $error);
        }

        if ($status >= 400) {
            throw new RuntimeException('Zoom API request failed with status ' . $status . ': ' . $response);
        }

        if ($status === 204 || $response === '') {
            return [];
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : [];
    }
}
