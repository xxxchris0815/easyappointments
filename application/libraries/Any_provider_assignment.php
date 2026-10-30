<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Selects a provider when the customer books with "Any Provider".
 *
 * Modes:
 * - most_available: current EA behavior (provider with most free hours that day)
 * - round_robin: rotate among candidates who can take the slot
 * - weighted_round_robin: same rotation, but providers appear more often by weight
 */
class Any_provider_assignment
{
    protected EA_Controller|CI_Controller $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('providers_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->library('availability');
    }

    /**
     * Pick a provider for the given service/date/hour.
     *
     * On reschedule, pass the current assignee as $preferred_provider_id: if that
     * provider can still take the slot they are kept (round-robin counter is not
     * advanced). Only when they are unavailable does the configured mode run.
     *
     * @throws Exception
     */
    public function select(
        int $service_id,
        string $date,
        ?string $hour = null,
        ?int $exclude_appointment_id = null,
        ?int $preferred_provider_id = null,
    ): ?int {
        $report = $this->build_report(
            $service_id,
            $date,
            $hour,
            $exclude_appointment_id,
            $preferred_provider_id,
            dry_run: false,
        );

        $this->append_log($report);

        return $report['selection']['selected_provider_id'];
    }

    /**
     * Dry-run the assignment and return the full calculation basis.
     *
     * Does not advance the round-robin counter and does not write a production log
     * entry (unless $persist_log is true).
     *
     * @throws Exception
     */
    public function explain(
        int $service_id,
        string $date,
        ?string $hour = null,
        ?int $exclude_appointment_id = null,
        ?int $preferred_provider_id = null,
        bool $persist_log = false,
    ): array {
        $report = $this->build_report(
            $service_id,
            $date,
            $hour,
            $exclude_appointment_id,
            $preferred_provider_id,
            dry_run: true,
        );

        if ($persist_log) {
            $this->append_log($report);
        }

        return $report;
    }

    /**
     * Build candidate list: providers who offer the service and can take the slot.
     *
     * @return array<int, array{id:int, available_hours_count:int, weight:int}>
     *
     * @throws Exception
     */
    public function collect_candidates(
        int $service_id,
        string $date,
        ?string $hour = null,
        ?int $exclude_appointment_id = null,
    ): array {
        $report = $this->build_report(
            $service_id,
            $date,
            $hour,
            $exclude_appointment_id,
            preferred_provider_id: null,
            dry_run: true,
        );

        return $report['candidates'];
    }

    /**
     * Read recent assignment decision log entries (newest last).
     *
     * @return list<array<string, mixed>>
     */
    public function read_logs(int $limit = 100): array
    {
        $path = $this->log_file_path();
        $limit = max(1, min(500, $limit));

        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false || !$lines) {
            return [];
        }

        $slice = array_slice($lines, -$limit);
        $entries = [];

        foreach ($slice as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Full calculation report used by both select() and explain().
     *
     * @throws Exception
     */
    protected function build_report(
        int $service_id,
        string $date,
        ?string $hour,
        ?int $exclude_appointment_id,
        ?int $preferred_provider_id,
        bool $dry_run,
    ): array {
        $mode = setting('any_provider_selection_mode', ANY_PROVIDER_MODE_MOST_AVAILABLE);
        $rr_counter = max(0, (int) setting('any_provider_rr_counter', '0'));
        $service = $this->CI->services_model->find($service_id);
        $available_providers = $this->CI->providers_model->get_available_providers(true);

        $providers = [];
        $candidates = [];

        foreach ($available_providers as $provider) {
            $provider_id = (int) $provider['id'];
            $name = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
            $weight = max(1, (int) ($provider['settings']['any_provider_weight'] ?? 1));
            $offers_service = in_array($service_id, $provider['services'] ?? [], false);
            $available_hours = [];
            $rejection_reason = null;

            if (!$offers_service) {
                $rejection_reason = 'does_not_offer_service';
            } else {
                $available_hours = $this->CI->availability->get_available_hours(
                    $date,
                    $service,
                    $provider,
                    $exclude_appointment_id,
                );

                if (!$available_hours) {
                    $rejection_reason = 'no_available_hours';
                } elseif (!empty($hour) && !in_array($hour, $available_hours, false)) {
                    $rejection_reason = 'slot_unavailable';
                }
            }

            $is_candidate = $rejection_reason === null;
            $row = [
                'id' => $provider_id,
                'name' => $name !== '' ? $name : ('#' . $provider_id),
                'email' => (string) ($provider['email'] ?? ''),
                'offers_service' => $offers_service,
                'available_hours' => array_values($available_hours ?: []),
                'available_hours_count' => count($available_hours ?: []),
                'can_take_slot' => $is_candidate,
                'weight' => $weight,
                'is_candidate' => $is_candidate,
                'rejection_reason' => $rejection_reason,
            ];

            $providers[] = $row;

            if ($is_candidate) {
                $candidates[] = [
                    'id' => $provider_id,
                    'name' => $row['name'],
                    'available_hours_count' => $row['available_hours_count'],
                    'weight' => $weight,
                ];
            }
        }

        usort($providers, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        usort($candidates, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);

        $preferred = [
            'provider_id' => $preferred_provider_id,
            'kept' => false,
            'reason' => null,
        ];

        $selected_id = null;
        $selection_basis = [];
        $rr_counter_after = $rr_counter;
        $selection_method = $mode;

        if (!$candidates) {
            $selection_method = 'none';
            $selection_basis = [
                'message' => 'No providers can take this slot.',
            ];
            $preferred['reason'] = $preferred_provider_id
                ? 'preferred_provider_not_available_and_no_candidates'
                : 'no_candidates';
        } elseif ($preferred_provider_id) {
            foreach ($candidates as $candidate) {
                if ((int) $candidate['id'] === (int) $preferred_provider_id) {
                    $selected_id = (int) $preferred_provider_id;
                    $preferred['kept'] = true;
                    $preferred['reason'] = 'preferred_provider_still_available';
                    $selection_method = 'preferred_provider';
                    $selection_basis = [
                        'message' => 'Kept current/preferred provider because they can still take the slot.',
                        'rr_counter_unchanged' => $rr_counter,
                    ];
                    break;
                }
            }

            if ($selected_id === null) {
                $preferred['reason'] = 'preferred_provider_not_in_candidates';
            }
        }

        if ($selected_id === null && $candidates) {
            [$selected_id, $selection_basis, $rr_counter_after] = match ($mode) {
                ANY_PROVIDER_MODE_ROUND_ROBIN => $this->select_round_robin($candidates, $rr_counter, $dry_run),
                ANY_PROVIDER_MODE_WEIGHTED_ROUND_ROBIN => $this->select_weighted_round_robin(
                    $candidates,
                    $rr_counter,
                    $dry_run,
                ),
                default => $this->select_most_available($candidates, $rr_counter),
            };
            $selection_method = $mode;
        }

        $selected_name = null;

        foreach ($providers as $provider) {
            if ((int) $provider['id'] === (int) $selected_id) {
                $selected_name = $provider['name'];
                break;
            }
        }

        return [
            'timestamp' => date('c'),
            'dry_run' => $dry_run,
            'input' => [
                'service_id' => $service_id,
                'service_name' => (string) ($service['name'] ?? ''),
                'date' => $date,
                'hour' => $hour,
                'exclude_appointment_id' => $exclude_appointment_id,
                'preferred_provider_id' => $preferred_provider_id,
            ],
            'settings' => [
                'display_any_provider' => filter_var(setting('display_any_provider', '0'), FILTER_VALIDATE_BOOLEAN),
                'mode' => $mode,
                'rr_counter' => $rr_counter,
                'rr_counter_after' => $rr_counter_after,
            ],
            'providers' => $providers,
            'candidates' => $candidates,
            'candidate_count' => count($candidates),
            'preferred' => $preferred,
            'selection' => [
                'method' => $selection_method,
                'selected_provider_id' => $selected_id,
                'selected_provider_name' => $selected_name,
                'basis' => $selection_basis,
            ],
        ];
    }

    /**
     * @param list<array{id:int, available_hours_count:int, weight:int, name?:string}> $candidates
     *
     * @return array{0:?int, 1:array<string, mixed>, 2:int}
     */
    protected function select_most_available(array $candidates, int $rr_counter): array
    {
        $best_id = null;
        $max_hours = -1;
        $comparison = [];

        foreach ($candidates as $candidate) {
            $comparison[] = [
                'id' => $candidate['id'],
                'name' => $candidate['name'] ?? null,
                'available_hours_count' => $candidate['available_hours_count'],
            ];

            if ($candidate['available_hours_count'] > $max_hours) {
                $max_hours = $candidate['available_hours_count'];
                $best_id = $candidate['id'];
            }
        }

        return [
            $best_id,
            [
                'message' => 'Selected provider with the most free hours that day.',
                'max_available_hours_count' => $max_hours,
                'comparison' => $comparison,
            ],
            $rr_counter,
        ];
    }

    /**
     * @param list<array{id:int, available_hours_count:int, weight:int, name?:string}> $candidates
     *
     * @return array{0:?int, 1:array<string, mixed>, 2:int}
     */
    protected function select_round_robin(array $candidates, int $rr_counter, bool $dry_run): array
    {
        $index = $rr_counter % count($candidates);
        $selected = $candidates[$index]['id'];
        $rr_after = $rr_counter + 1;

        if (!$dry_run) {
            setting(['any_provider_rr_counter' => (string) $rr_after]);
        }

        return [
            $selected,
            [
                'message' => 'Round-robin among candidates sorted by provider id.',
                'candidate_ids' => array_column($candidates, 'id'),
                'rr_counter' => $rr_counter,
                'index' => $index,
                'formula' => $rr_counter . ' % ' . count($candidates) . ' = ' . $index,
                'rr_counter_after' => $rr_after,
                'counter_advanced' => !$dry_run,
            ],
            $rr_after,
        ];
    }

    /**
     * @param list<array{id:int, available_hours_count:int, weight:int, name?:string}> $candidates
     *
     * @return array{0:?int, 1:array<string, mixed>, 2:int}
     */
    protected function select_weighted_round_robin(array $candidates, int $rr_counter, bool $dry_run): array
    {
        $sequence = [];

        foreach ($candidates as $candidate) {
            $weight = max(1, (int) $candidate['weight']);

            for ($i = 0; $i < $weight; $i++) {
                $sequence[] = $candidate['id'];
            }
        }

        if (!$sequence) {
            return [null, ['message' => 'Weighted sequence was empty.'], $rr_counter];
        }

        $index = $rr_counter % count($sequence);
        $selected = $sequence[$index];
        $rr_after = $rr_counter + 1;

        if (!$dry_run) {
            setting(['any_provider_rr_counter' => (string) $rr_after]);
        }

        return [
            $selected,
            [
                'message' => 'Weighted round-robin: each candidate appears weight times in the rotation.',
                'weights' => array_map(
                    static fn(array $c): array => [
                        'id' => $c['id'],
                        'name' => $c['name'] ?? null,
                        'weight' => max(1, (int) $c['weight']),
                    ],
                    $candidates,
                ),
                'sequence' => $sequence,
                'rr_counter' => $rr_counter,
                'index' => $index,
                'formula' => $rr_counter . ' % ' . count($sequence) . ' = ' . $index,
                'rr_counter_after' => $rr_after,
                'counter_advanced' => !$dry_run,
            ],
            $rr_after,
        ];
    }

    protected function log_file_path(): string
    {
        $dir = config('log_path') ?: (FCPATH . 'storage/logs/');

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'any_provider_assignment.log';
    }

    protected function append_log(array $report): void
    {
        $path = $this->log_file_path();

        $entry = [
            'timestamp' => $report['timestamp'] ?? date('c'),
            'dry_run' => (bool) ($report['dry_run'] ?? false),
            'input' => $report['input'] ?? [],
            'settings' => $report['settings'] ?? [],
            'candidate_count' => $report['candidate_count'] ?? 0,
            'candidates' => array_map(
                static fn(array $c): array => [
                    'id' => $c['id'],
                    'available_hours_count' => $c['available_hours_count'],
                    'weight' => $c['weight'],
                ],
                $report['candidates'] ?? [],
            ),
            'preferred' => $report['preferred'] ?? [],
            'selection' => $report['selection'] ?? [],
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
