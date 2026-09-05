<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Builds an optional external redirect URL after a successful booking.
 *
 * Template example:
 * https://example.com/thanks?date={date}&time={time}&name={customer_name}
 */
class Booking_success_redirect
{
    /**
     * Placeholder tokens supported in the redirect URL template.
     *
     * @return list<string>
     */
    public function available_placeholders(): array
    {
        return [
            'date',
            'time',
            'end_time',
            'start_datetime',
            'end_datetime',
            'datetime',
            'customer_first_name',
            'customer_last_name',
            'customer_name',
            'customer_email',
            'customer_phone',
            'service',
            'provider',
            'provider_first_name',
            'provider_last_name',
            'appointment_id',
            'appointment_hash',
            'location',
            'meeting_link',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ];
    }

    /**
     * Whether a stored template is empty or a valid absolute http(s) URL with placeholders.
     */
    public function is_valid_template(string $template): bool
    {
        $template = trim($template);

        if ($template === '') {
            return true;
        }

        $probe = preg_replace('/\{[a-z0-9_]+\}/i', 'placeholder', $template);
        $parts = parse_url((string) $probe);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
    }

    /**
     * Resolve the configured redirect URL for the given booking records.
     *
     * @return string|null Absolute URL or null when redirect is disabled/invalid.
     */
    public function build(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        ?string $template = null,
    ): ?string {
        $template = trim((string) ($template ?? setting('booking_success_redirect_url', '')));

        if ($template === '' || !$this->is_valid_template($template)) {
            return null;
        }

        $start = (string) ($appointment['start_datetime'] ?? '');
        $end = (string) ($appointment['end_datetime'] ?? '');
        $customer_first = (string) ($customer['first_name'] ?? '');
        $customer_last = (string) ($customer['last_name'] ?? '');
        $provider_first = (string) ($provider['first_name'] ?? '');
        $provider_last = (string) ($provider['last_name'] ?? '');

        $values = [
            'date' => $start !== '' ? format_date($start) : '',
            'time' => $start !== '' ? format_time($start) : '',
            'end_time' => $end !== '' ? format_time($end) : '',
            'start_datetime' => $start,
            'end_datetime' => $end,
            'datetime' => $start !== '' ? format_date_time($start) : '',
            'customer_first_name' => $customer_first,
            'customer_last_name' => $customer_last,
            'customer_name' => trim($customer_first . ' ' . $customer_last),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'customer_phone' => (string) ($customer['phone_number'] ?? ''),
            'service' => (string) ($service['name'] ?? ''),
            'provider' => trim($provider_first . ' ' . $provider_last),
            'provider_first_name' => $provider_first,
            'provider_last_name' => $provider_last,
            'appointment_id' => (string) ($appointment['id'] ?? ''),
            'appointment_hash' => (string) ($appointment['hash'] ?? ''),
            'location' => (string) ($appointment['location'] ?? ''),
            'meeting_link' => (string) ($appointment['meeting_link'] ?? ''),
            'utm_source' => (string) ($appointment['utm_source'] ?? ''),
            'utm_medium' => (string) ($appointment['utm_medium'] ?? ''),
            'utm_campaign' => (string) ($appointment['utm_campaign'] ?? ''),
            'utm_term' => (string) ($appointment['utm_term'] ?? ''),
            'utm_content' => (string) ($appointment['utm_content'] ?? ''),
        ];

        $url = preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($values): string {
                $key = strtolower($matches[1]);

                if (!array_key_exists($key, $values)) {
                    return '';
                }

                return rawurlencode((string) $values[$key]);
            },
            $template,
        );

        $parts = parse_url((string) $url);

        if (
            empty($parts['scheme']) ||
            empty($parts['host']) ||
            !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            return null;
        }

        return $url;
    }
}
