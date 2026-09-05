<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Appointment reminders scheduler/worker.
 * ---------------------------------------------------------------------------- */

/**
 * Reminders library.
 *
 * @package Libraries
 */
class Reminders
{
    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->model('appointment_reminder_deliveries_model');
        $this->CI->load->model('customers_model');
        $this->CI->load->model('providers_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('settings_model');

        $this->CI->load->library('notifications');
        $this->CI->load->library('webhooks_client');
    }

    /**
     * Whether reminders are enabled.
     */
    public function is_enabled(): bool
    {
        return filter_var(setting('appointment_reminders_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Normalized reminder rules from settings.
     *
     * @return array<int, array{id:string,offset:int,unit:string,channels:array<int,string>}>
     */
    public function get_rules(): array
    {
        $raw = setting('appointment_reminders', '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (!is_array($decoded)) {
            return [];
        }

        $rules = [];

        foreach ($decoded as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $offset = (int) ($rule['offset'] ?? 0);
            $unit = strtolower((string) ($rule['unit'] ?? 'hours'));
            $channels = array_values(
                array_intersect(
                    array_map('strval', (array) ($rule['channels'] ?? [])),
                    ['email', 'webhook'],
                ),
            );

            if ($offset < 0 || !in_array($unit, ['minutes', 'hours', 'days'], true) || empty($channels)) {
                continue;
            }

            $id = trim((string) ($rule['id'] ?? ''));
            if ($id === '') {
                $id = 'r' . ($index + 1);
            }

            $rules[] = [
                'id' => $id,
                'offset' => $offset,
                'unit' => $unit,
                'channels' => $channels,
            ];
        }

        return $rules;
    }

    /**
     * Rebuild pending reminder deliveries for an appointment.
     */
    public function schedule_for_appointment(array $appointment): void
    {
        if (empty($appointment['id'])) {
            return;
        }

        $appointment_id = (int) $appointment['id'];

        if (!$this->is_enabled() || $this->CI->appointments_model->is_cancelled($appointment)) {
            $this->CI->appointment_reminder_deliveries_model->skip_pending_for_appointment($appointment_id);
            return;
        }

        if (!empty($appointment['is_unavailability'])) {
            $this->CI->appointment_reminder_deliveries_model->delete_for_appointment($appointment_id);
            return;
        }

        $start = strtotime((string) ($appointment['start_datetime'] ?? ''));
        if ($start === false || $start <= time()) {
            $this->CI->appointment_reminder_deliveries_model->skip_pending_for_appointment($appointment_id);
            return;
        }

        $rows = [];

        $now = time();

        foreach ($this->get_rules() as $rule) {
            $due = $this->calculate_due_datetime($appointment['start_datetime'], $rule['offset'], $rule['unit']);

            if ($due === null) {
                continue;
            }

            // If the reminder moment is already past, do not queue it for immediate send.
            // Otherwise a 14h rule and a 5m rule can both fire on the next cron tick.
            $due_ts = strtotime($due);
            if ($due_ts === false || $due_ts <= $now) {
                continue;
            }

            foreach ($rule['channels'] as $channel) {
                $rows[] = [
                    'reminder_key' => $rule['id'],
                    'channel' => $channel,
                    'due_datetime' => $due,
                    'sent_at' => null,
                    'status' => 'pending',
                    'error_message' => null,
                ];
            }
        }

        $this->CI->appointment_reminder_deliveries_model->replace_pending_for_appointment($appointment_id, $rows);
    }

    /**
     * Clear/skip pending reminders after cancel.
     */
    public function clear_for_appointment(int $appointment_id): void
    {
        $this->CI->appointment_reminder_deliveries_model->skip_pending_for_appointment($appointment_id);
    }

    /**
     * Process due reminder deliveries.
     *
     * @return array{processed:int,sent:int,failed:int,skipped:int}
     */
    public function run(): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        if (!$this->is_enabled()) {
            return $stats;
        }

        $now = date('Y-m-d H:i:s');
        $due = $this->CI->appointment_reminder_deliveries_model->get_due_pending($now);
        $rules_by_id = [];

        foreach ($this->get_rules() as $rule) {
            $rules_by_id[$rule['id']] = $rule;
        }

        foreach ($due as $delivery) {
            $stats['processed']++;

            try {
                $appointment = $this->CI->appointments_model->find((int) $delivery['id_appointments']);

                if ($this->CI->appointments_model->is_cancelled($appointment)) {
                    $this->CI->appointment_reminder_deliveries_model->mark_skipped(
                        (int) $delivery['id'],
                        'Appointment cancelled',
                    );
                    $stats['skipped']++;
                    continue;
                }

                if (strtotime($appointment['start_datetime']) <= time()) {
                    $this->CI->appointment_reminder_deliveries_model->mark_skipped(
                        (int) $delivery['id'],
                        'Appointment already started',
                    );
                    $stats['skipped']++;
                    continue;
                }

                $rule = $rules_by_id[$delivery['reminder_key']] ?? [
                    'id' => $delivery['reminder_key'],
                    'offset' => null,
                    'unit' => null,
                    'channels' => [$delivery['channel']],
                ];

                $provider = $this->CI->providers_model->find((int) $appointment['id_users_provider']);
                $customer = $this->CI->customers_model->find((int) $appointment['id_users_customer']);
                $service = $this->CI->services_model->find((int) $appointment['id_services']);

                if ($delivery['channel'] === 'email') {
                    $this->CI->notifications->notify_appointment_reminder(
                        $appointment,
                        $service,
                        $provider,
                        $customer,
                        $rule,
                    );
                } elseif ($delivery['channel'] === 'webhook') {
                    $this->CI->webhooks_client->trigger_appointment_reminder(
                        $appointment,
                        $customer,
                        $provider,
                        $service,
                        $rule,
                    );
                } else {
                    $this->CI->appointment_reminder_deliveries_model->mark_skipped(
                        (int) $delivery['id'],
                        'Unknown channel',
                    );
                    $stats['skipped']++;
                    continue;
                }

                $this->CI->appointment_reminder_deliveries_model->mark_sent((int) $delivery['id']);
                $stats['sent']++;
            } catch (Throwable $e) {
                log_message('error', 'Reminder delivery #' . ($delivery['id'] ?? '?') . ' failed: ' . $e->getMessage());
                $this->CI->appointment_reminder_deliveries_model->mark_failed(
                    (int) $delivery['id'],
                    $e->getMessage(),
                );
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Calculate due datetime from appointment start.
     */
    protected function calculate_due_datetime(string $start_datetime, int $offset, string $unit): ?string
    {
        try {
            $start = new DateTime($start_datetime);
        } catch (Throwable) {
            return null;
        }

        $interval_map = [
            'minutes' => 'PT' . $offset . 'M',
            'hours' => 'PT' . $offset . 'H',
            'days' => 'P' . $offset . 'D',
        ];

        if (!isset($interval_map[$unit])) {
            return null;
        }

        $due = clone $start;
        $due->sub(new DateInterval($interval_map[$unit]));

        return $due->format('Y-m-d H:i:s');
    }
}
