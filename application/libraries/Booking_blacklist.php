<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Blocks public bookings that match admin-configured email/phone/Mautic IDs.
 *
 * Phone entries support matching with or without country calling code.
 */
class Booking_blacklist
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_MAUTIC_ID = 'mautic_id';

    public const PHONE_MATCH_WITH_COUNTRY = 'with_country_code';
    public const PHONE_MATCH_WITHOUT_COUNTRY = 'without_country_code';

    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('admins_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->library('email_messages');
    }

    /**
     * @return list<array{type:string,value:string,phone_match_mode:?string,note:string}>
     */
    public function get_entries(): array
    {
        $raw = setting('booking_blacklist', '[]');
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalize_entry($row);

            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }

        return $entries;
    }

    /**
     * Validate and sanitize entries before persisting.
     *
     * @param mixed $entries
     *
     * @return list<array{type:string,value:string,phone_match_mode:?string,note:string}>
     */
    public function sanitize_entries(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $clean = [];

        foreach ($entries as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalize_entry($row);

            if ($normalized !== null) {
                $clean[] = $normalized;
            }
        }

        return $clean;
    }

    /**
     * Find the first matching blacklist entry for the booking attempt.
     *
     * @return array{type:string,value:string,phone_match_mode:?string,note:string}|null
     */
    public function match(?string $email, ?string $phone, ?string $mautic_id): ?array
    {
        foreach ($this->get_entries() as $entry) {
            if ($this->entry_matches($entry, $email, $phone, $mautic_id)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Ignore the booking and notify admins (email + log).
     *
     * @param array{type:string,value:string,phone_match_mode:?string,note:string} $matched
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $appointment
     */
    public function report_blocked_attempt(
        array $matched,
        array $customer,
        array $appointment,
        ?string $mautic_id,
        ?string $ip = null,
    ): void {
        $payload = [
            'timestamp' => date('c'),
            'matched' => $matched,
            'mautic_id' => $mautic_id,
            'ip' => $ip,
            'customer' => [
                'first_name' => $customer['first_name'] ?? null,
                'last_name' => $customer['last_name'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone_number' => $customer['phone_number'] ?? null,
            ],
            'appointment' => [
                'start_datetime' => $appointment['start_datetime'] ?? null,
                'end_datetime' => $appointment['end_datetime'] ?? null,
                'id_services' => $appointment['id_services'] ?? null,
                'id_users_provider' => $appointment['id_users_provider'] ?? null,
            ],
        ];

        $this->append_log($payload);
        $this->notify_admins($payload);
    }

    /**
     * Pure phone comparison helper (also used by unit tests via reflection-free wrappers).
     */
    public function phones_match(string $booking_phone, string $blacklist_phone, string $mode): bool
    {
        $booking_digits = $this->digits_only($booking_phone);
        $blacklist_digits = $this->digits_only($blacklist_phone);

        if ($booking_digits === '' || $blacklist_digits === '') {
            return false;
        }

        if ($mode === self::PHONE_MATCH_WITH_COUNTRY) {
            return $this->normalize_international_digits($booking_digits)
                === $this->normalize_international_digits($blacklist_digits);
        }

        // without_country_code: compare national cores (ignore leading 0 / country prefix).
        $booking_core = $this->national_core($booking_digits);
        $blacklist_core = $this->national_core($blacklist_digits);

        if ($booking_core === '' || $blacklist_core === '') {
            return false;
        }

        if ($booking_core === $blacklist_core) {
            return true;
        }

        // Allow "+49 170…" vs blacklisted "0170…" / "170…"
        $min_len = 7;

        if (strlen($blacklist_core) < $min_len || strlen($booking_core) < $min_len) {
            return false;
        }

        return str_ends_with($booking_core, $blacklist_core)
            || str_ends_with($blacklist_core, $booking_core);
    }

    protected function entry_matches(array $entry, ?string $email, ?string $phone, ?string $mautic_id): bool
    {
        return match ($entry['type']) {
            self::TYPE_EMAIL => $this->emails_match((string) ($email ?? ''), $entry['value']),
            self::TYPE_PHONE => $this->phones_match(
                (string) ($phone ?? ''),
                $entry['value'],
                $entry['phone_match_mode'] ?? self::PHONE_MATCH_WITHOUT_COUNTRY,
            ),
            self::TYPE_MAUTIC_ID => $this->mautic_ids_match((string) ($mautic_id ?? ''), $entry['value']),
            default => false,
        };
    }

    protected function emails_match(string $email, string $blacklist_email): bool
    {
        $left = mb_strtolower(trim($email));
        $right = mb_strtolower(trim($blacklist_email));

        return $left !== '' && $right !== '' && $left === $right;
    }

    protected function mautic_ids_match(string $mautic_id, string $blacklist_id): bool
    {
        $left = trim($mautic_id);
        $right = trim($blacklist_id);

        return $left !== '' && $right !== '' && $left === $right;
    }

    protected function digits_only(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Normalize international forms: leading 00 → drop; keep country digits.
     */
    protected function normalize_international_digits(string $digits): string
    {
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * National core for country-agnostic comparison:
     * strip 00-prefix, then strip a single leading trunk 0.
     */
    protected function national_core(string $digits): string
    {
        $digits = $this->normalize_international_digits($digits);

        // If number still has a trunk 0 after country code was not provided (0170…).
        if (str_starts_with($digits, '0')) {
            return ltrim($digits, '0');
        }

        return $digits;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{type:string,value:string,phone_match_mode:?string,note:string}|null
     */
    protected function normalize_entry(array $row): ?array
    {
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        $value = trim((string) ($row['value'] ?? ''));
        $note = trim((string) ($row['note'] ?? ''));

        if ($value === '') {
            return null;
        }

        if (!in_array($type, [self::TYPE_EMAIL, self::TYPE_PHONE, self::TYPE_MAUTIC_ID], true)) {
            return null;
        }

        $phone_match_mode = null;

        if ($type === self::TYPE_EMAIL) {
            $value = mb_strtolower($value);

            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
        }

        if ($type === self::TYPE_PHONE) {
            $mode = (string) ($row['phone_match_mode'] ?? self::PHONE_MATCH_WITHOUT_COUNTRY);
            $phone_match_mode = $mode === self::PHONE_MATCH_WITH_COUNTRY
                ? self::PHONE_MATCH_WITH_COUNTRY
                : self::PHONE_MATCH_WITHOUT_COUNTRY;

            if ($this->digits_only($value) === '') {
                return null;
            }
        }

        if ($type === self::TYPE_MAUTIC_ID && !preg_match('/^\d+$/', $value)) {
            return null;
        }

        return [
            'type' => $type,
            'value' => mb_substr($value, 0, 191),
            'phone_match_mode' => $phone_match_mode,
            'note' => mb_substr($note, 0, 255),
        ];
    }

    protected function append_log(array $payload): void
    {
        $dir = config('log_path') ?: (FCPATH . 'storage/logs/');

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'booking_blacklist.log';
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    protected function notify_admins(array $payload): void
    {
        $admins = $this->CI->admins_model->get();
        $matched = $payload['matched'] ?? [];
        $customer = $payload['customer'] ?? [];
        $appointment = $payload['appointment'] ?? [];

        $subject = lang('booking_blacklist_admin_subject');

        $lines = [
            '<p>' . html_escape(lang('booking_blacklist_admin_intro')) . '</p>',
            '<ul>',
            '<li><strong>' . html_escape(lang('booking_blacklist_entry_type')) . ':</strong> ' . html_escape((string) ($matched['type'] ?? '')) . '</li>',
            '<li><strong>' . html_escape(lang('booking_blacklist_entry_value')) . ':</strong> ' . html_escape((string) ($matched['value'] ?? '')) . '</li>',
        ];

        if (!empty($matched['phone_match_mode'])) {
            $lines[] =
                '<li><strong>' .
                html_escape(lang('booking_blacklist_phone_match_mode')) .
                ':</strong> ' .
                html_escape((string) $matched['phone_match_mode']) .
                '</li>';
        }

        if (!empty($matched['note'])) {
            $lines[] =
                '<li><strong>' .
                html_escape(lang('notes')) .
                ':</strong> ' .
                html_escape((string) $matched['note']) .
                '</li>';
        }

        $lines[] =
            '<li><strong>' .
            html_escape(lang('customer')) .
            ':</strong> ' .
            html_escape(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) .
            '</li>';
        $lines[] =
            '<li><strong>' .
            html_escape(lang('email')) .
            ':</strong> ' .
            html_escape((string) ($customer['email'] ?? '')) .
            '</li>';
        $lines[] =
            '<li><strong>' .
            html_escape(lang('phone_number')) .
            ':</strong> ' .
            html_escape((string) ($customer['phone_number'] ?? '')) .
            '</li>';
        $lines[] =
            '<li><strong>Mautic ID:</strong> ' .
            html_escape((string) ($payload['mautic_id'] ?? '')) .
            '</li>';
        $lines[] =
            '<li><strong>' .
            html_escape(lang('date')) .
            ':</strong> ' .
            html_escape((string) ($appointment['start_datetime'] ?? '')) .
            '</li>';
        $lines[] =
            '<li><strong>IP:</strong> ' .
            html_escape((string) ($payload['ip'] ?? '')) .
            '</li>';
        $lines[] = '</ul>';
        $lines[] = '<p>' . html_escape(lang('booking_blacklist_admin_ignored')) . '</p>';

        $html = implode("\n", $lines);

        foreach ($admins as $admin) {
            if (($admin['settings']['notifications'] ?? '1') === '0') {
                continue;
            }

            $email = trim((string) ($admin['email'] ?? ''));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                $this->CI->email_messages->send_test_email($email, $subject, $html);
            } catch (Throwable $e) {
                log_message(
                    'error',
                    'Booking blacklist admin notify failed for ' . $email . ': ' . $e->getMessage(),
                );
            }
        }
    }
}
