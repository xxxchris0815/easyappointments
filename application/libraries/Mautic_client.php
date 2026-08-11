<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Mautic API client for contact/lead lookup.
 * ---------------------------------------------------------------------------- */

/**
 * Mautic Client Library
 *
 * Looks up Mautic contacts via REST API v2 or an optional webhook fallback.
 *
 * @package Libraries
 */
class Mautic_client
{
    /**
     * Whether any Mautic lookup method is enabled.
     */
    public function is_enabled(): bool
    {
        return filter_var(setting('mautic_lead_lookup_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether Mautic lookup is enabled and has usable credentials/config.
     */
    public function is_configured(): bool
    {
        if (!$this->is_enabled()) {
            return false;
        }

        $mode = setting('mautic_lookup_mode', 'api');

        if ($mode === 'webhook') {
            return trim((string) setting('mautic_lead_lookup_url')) !== '';
        }

        return $this->has_api_credentials() || trim((string) setting('mautic_lead_lookup_url')) !== '';
    }

    /**
     * Lookup a Mautic contact/lead by internal numeric id.
     *
     * @return array{first_name:?string,last_name:?string,email:?string,phone_number:?string,l_id:string}
     */
    public function lookup_by_id(string $l_id): array
    {
        if (!$this->is_configured()) {
            throw new RuntimeException('Mautic lead lookup is disabled or not configured.');
        }

        if ($l_id === '' || !preg_match('/^\d+$/', $l_id)) {
            throw new InvalidArgumentException('A valid internal Mautic lead id (l_id) is required.');
        }

        $mode = setting('mautic_lookup_mode', 'api');

        if ($mode === 'webhook' || !$this->has_api_credentials()) {
            return $this->lookup_via_webhook($l_id);
        }

        return $this->lookup_via_api($l_id);
    }

    /**
     * Whether API credentials are configured.
     */
    public function has_api_credentials(): bool
    {
        return trim((string) setting('mautic_api_url')) !== '' &&
            trim((string) setting('mautic_api_username')) !== '' &&
            trim((string) setting('mautic_api_password')) !== '';
    }

    /**
     * Lookup contact through Mautic REST API `/api/contacts/{id}`.
     */
    protected function lookup_via_api(string $l_id): array
    {
        $base = rtrim((string) setting('mautic_api_url'), '/');
        $username = (string) setting('mautic_api_username');
        $password = (string) setting('mautic_api_password');

        $url = $base . '/api/contacts/' . rawurlencode($l_id);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Mautic API request failed: ' . $error);
        }

        if ($status >= 400) {
            throw new RuntimeException('Mautic API request failed with status ' . $status . ': ' . $response);
        }

        $payload = json_decode($response, true);

        if (!is_array($payload)) {
            return $this->empty_lead($l_id);
        }

        return $this->normalize_api_contact($payload, $l_id);
    }

    /**
     * Lookup lead through the configured webhook URL.
     */
    protected function lookup_via_webhook(string $l_id): array
    {
        $base = rtrim((string) setting('mautic_lead_lookup_url'), '?&');

        if ($base === '') {
            throw new RuntimeException('Mautic webhook lookup URL is not configured.');
        }

        $url = $base . (str_contains($base, '?') ? '&' : '?') . 'l_id=' . rawurlencode($l_id);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Mautic webhook lookup failed: ' . $error);
        }

        if ($status >= 400) {
            throw new RuntimeException('Mautic webhook lookup failed with status ' . $status);
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return $this->empty_lead($l_id);
        }

        return [
            'first_name' => $data['first_name'] ?? ($data['firstname'] ?? null),
            'last_name' => $data['last_name'] ?? ($data['lastname'] ?? null),
            'email' => $data['email'] ?? null,
            'phone_number' => $data['phone_number'] ?? ($data['phone'] ?? ($data['mobile'] ?? null)),
            'l_id' => $l_id,
        ];
    }

    /**
     * Normalize Mautic API contact payload into booking form fields.
     */
    protected function normalize_api_contact(array $payload, string $l_id): array
    {
        $contact = $payload['contact'] ?? $payload;
        $fields = $contact['fields']['core'] ?? ($contact['fields']['all'] ?? []);

        $read = static function (array $fields, string $key): ?string {
            if (!isset($fields[$key])) {
                return null;
            }

            $value = $fields[$key];

            if (is_array($value)) {
                $value = $value['value'] ?? null;
            }

            if ($value === null || $value === '') {
                return null;
            }

            return (string) $value;
        };

        $phone =
            $read($fields, 'mobile') ??
            $read($fields, 'phone') ??
            $read($fields, 'phone_number');

        return [
            'first_name' => $read($fields, 'firstname') ?? $read($fields, 'first_name'),
            'last_name' => $read($fields, 'lastname') ?? $read($fields, 'last_name'),
            'email' => $read($fields, 'email'),
            'phone_number' => $phone,
            'l_id' => $l_id,
        ];
    }

    protected function empty_lead(string $l_id): array
    {
        return [
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'phone_number' => null,
            'l_id' => $l_id,
        ];
    }
}
