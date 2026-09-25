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
     * @throws Exception
     */
    public function select(int $service_id, string $date, ?string $hour = null): ?int
    {
        $candidates = $this->collect_candidates($service_id, $date, $hour);

        if (!$candidates) {
            return null;
        }

        $mode = setting('any_provider_selection_mode', ANY_PROVIDER_MODE_MOST_AVAILABLE);

        return match ($mode) {
            ANY_PROVIDER_MODE_ROUND_ROBIN => $this->select_round_robin($candidates),
            ANY_PROVIDER_MODE_WEIGHTED_ROUND_ROBIN => $this->select_weighted_round_robin($candidates),
            default => $this->select_most_available($candidates),
        };
    }

    /**
     * Build candidate list: providers who offer the service and can take the slot.
     *
     * @return array<int, array{id:int, available_hours_count:int, weight:int}>
     *
     * @throws Exception
     */
    public function collect_candidates(int $service_id, string $date, ?string $hour = null): array
    {
        $available_providers = $this->CI->providers_model->get_available_providers(true);
        $service = $this->CI->services_model->find($service_id);
        $candidates = [];

        foreach ($available_providers as $provider) {
            if (!in_array($service_id, $provider['services'] ?? [], false)) {
                continue;
            }

            $available_hours = $this->CI->availability->get_available_hours($date, $service, $provider);

            if (!$available_hours) {
                continue;
            }

            if (!empty($hour) && !in_array($hour, $available_hours)) {
                continue;
            }

            $candidates[] = [
                'id' => (int) $provider['id'],
                'available_hours_count' => count($available_hours),
                'weight' => max(1, (int) ($provider['settings']['any_provider_weight'] ?? 1)),
            ];
        }

        usort($candidates, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);

        return $candidates;
    }

    /**
     * Legacy EA behavior: prefer the provider with the most free hours that day.
     */
    protected function select_most_available(array $candidates): ?int
    {
        $best_id = null;
        $max_hours = -1;

        foreach ($candidates as $candidate) {
            if ($candidate['available_hours_count'] > $max_hours) {
                $max_hours = $candidate['available_hours_count'];
                $best_id = $candidate['id'];
            }
        }

        return $best_id;
    }

    /**
     * Round-robin among candidates.
     */
    protected function select_round_robin(array $candidates): ?int
    {
        if (!$candidates) {
            return null;
        }

        $counter = max(0, (int) setting('any_provider_rr_counter', '0'));
        $index = $counter % count($candidates);
        $selected = $candidates[$index]['id'];

        setting(['any_provider_rr_counter' => (string) ($counter + 1)]);

        return $selected;
    }

    /**
     * Weighted round-robin: each provider is listed `weight` times in the rotation.
     */
    protected function select_weighted_round_robin(array $candidates): ?int
    {
        if (!$candidates) {
            return null;
        }

        $sequence = [];

        foreach ($candidates as $candidate) {
            $weight = max(1, (int) $candidate['weight']);

            for ($i = 0; $i < $weight; $i++) {
                $sequence[] = $candidate['id'];
            }
        }

        if (!$sequence) {
            return null;
        }

        $counter = max(0, (int) setting('any_provider_rr_counter', '0'));
        $selected = $sequence[$counter % count($sequence)];

        setting(['any_provider_rr_counter' => (string) ($counter + 1)]);

        return $selected;
    }
}
