<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin screen to inspect Any Provider assignment calculations and recent decisions.
 */
class Any_provider_assignment_log extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->library('any_provider_assignment');

        if (can('edit', PRIV_SYSTEM_SETTINGS) === false && can('view', PRIV_SYSTEM_SETTINGS) === false) {
            show_error('Forbidden', 403);
        }
    }

    public function index(): void
    {
        method('get');

        $user_id = session('user_id');

        if (!$user_id) {
            redirect('login');
            return;
        }

        $services = $this->services_model->get();
        $providers = $this->providers_model->get();

        usort(
            $services,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')),
        );
        usort(
            $providers,
            static fn(array $a, array $b): int => strcasecmp(
                trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
                trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')),
            ),
        );

        $mode = setting('any_provider_selection_mode', ANY_PROVIDER_MODE_MOST_AVAILABLE);

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
            'any_provider_selection_mode' => $mode,
            'any_provider_rr_counter' => (int) setting('any_provider_rr_counter', '0'),
            'display_any_provider' => filter_var(setting('display_any_provider', '0'), FILTER_VALIDATE_BOOLEAN),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ]);

        html_vars([
            'page_title' => lang('any_provider_assignment_log'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'services' => $services,
            'providers' => $providers,
            'any_provider_selection_mode' => $mode,
            'any_provider_rr_counter' => (int) setting('any_provider_rr_counter', '0'),
            'display_any_provider' => filter_var(setting('display_any_provider', '0'), FILTER_VALIDATE_BOOLEAN),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
        ]);

        $this->load->view('pages/any_provider_assignment_log');
    }

    /**
     * Dry-run assignment calculation (JSON).
     */
    public function explain(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $service_id = (int) request('service_id');
            $date = trim((string) request('date', date('Y-m-d')));
            $hour = trim((string) request('hour', ''));
            $exclude_appointment_id = (int) request('exclude_appointment_id', 0);
            $preferred_provider_id = (int) request('preferred_provider_id', 0);

            if ($service_id <= 0) {
                throw new InvalidArgumentException('Invalid service_id.');
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException('Invalid date. Expected Y-m-d.');
            }

            if ($hour === '') {
                $hour = null;
            } elseif (!preg_match('/^\d{1,2}:\d{2}$/', $hour)) {
                throw new InvalidArgumentException('Invalid hour. Expected H:i.');
            } else {
                // Normalize "9:00" → "09:00" so it matches availability hours.
                [$h, $m] = array_map('intval', explode(':', $hour));
                $hour = sprintf('%02d:%02d', $h, $m);
            }

            $report = $this->any_provider_assignment->explain(
                $service_id,
                $date,
                $hour,
                $exclude_appointment_id > 0 ? $exclude_appointment_id : null,
                $preferred_provider_id > 0 ? $preferred_provider_id : null,
            );

            json_response([
                'success' => true,
                'report' => $report,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Recent persisted assignment decisions (JSON).
     */
    public function logs(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $limit = (int) request('limit', 100);
            $entries = $this->any_provider_assignment->read_logs($limit);

            json_response([
                'success' => true,
                'count' => count($entries),
                'entries' => $entries,
                'settings' => [
                    'mode' => setting('any_provider_selection_mode', ANY_PROVIDER_MODE_MOST_AVAILABLE),
                    'rr_counter' => (int) setting('any_provider_rr_counter', '0'),
                    'display_any_provider' => filter_var(
                        setting('display_any_provider', '0'),
                        FILTER_VALIDATE_BOOLEAN,
                    ),
                ],
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
