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
     * @return array{id:?string,join_url:?string}
     */
    public function sync_appointment(array $appointment, array $provider, array $service, array $customer): array
    {
        if (!$this->is_enabled()) {
            return ['id' => $appointment['id_zoom_meeting'] ?? null, 'join_url' => $appointment['meeting_link'] ?? null];
        }

        $zoom_user = $provider['settings']['zoom_email'] ?? $provider['email'] ?? null;

        if (empty($zoom_user)) {
            throw new RuntimeException('Provider Zoom email is not configured.');
        }

        $token = $this->get_access_token();

        $payload = [
            'topic' => !empty($service['name']) ? $service['name'] : 'Appointment',
            'type' => 2,
            'start_time' => (new DateTime($appointment['start_datetime'], new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'duration' => max(
                1,
                (int) round(
                    (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60,
                ),
            ),
            'timezone' => $provider['timezone'] ?? 'UTC',
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
        ];
    }

    /**
     * Delete a Zoom meeting.
     */
    public function delete_meeting(?string $meeting_id): void
    {
        if (!$this->is_enabled() || empty($meeting_id)) {
            return;
        }

        $this->request(
            'DELETE',
            'https://api.zoom.us/v2/meetings/' . rawurlencode($meeting_id),
            null,
            $this->get_access_token(),
        );
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
