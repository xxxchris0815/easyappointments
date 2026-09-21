<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Google Calendar sync status controller.
 *
 * Admin overview of per-provider Google sync state and filtered Google sync logs.
 */
class Google_calendar_sync_status extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('roles_model');
        $this->load->model('providers_model');
        $this->load->model('unavailabilities_model');

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

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'google_sync_feature' => setting('google_sync_feature', '0'),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
        ]);

        html_vars([
            'page_title' => lang('google_calendar_sync_status'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'google_sync_feature' => setting('google_sync_feature', '0'),
            'can_edit_system_settings' => can('edit', PRIV_SYSTEM_SETTINGS),
        ]);

        $this->load->view('pages/google_calendar_sync_status');
    }

    /**
     * Return provider sync status rows (JSON).
     */
    public function providers(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $sync_filter = strtolower(trim((string) request('sync', 'all')));
            $connected_filter = strtolower(trim((string) request('connected', 'all')));
            $search = mb_strtolower(trim((string) request('search', '')));

            $providers = $this->providers_model->get();
            $rows = [];

            foreach ($providers as $provider) {
                $settings = $provider['settings'] ?? [];
                $sync_enabled = filter_var($settings['google_sync'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $token = trim((string) ($settings['google_token'] ?? ''));
                $connected = $token !== '' && $token !== 'null' && $token !== '[]' && $token !== '{}';
                $calendar = trim((string) ($settings['google_calendar'] ?? ''));
                $name = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
                $email = (string) ($provider['email'] ?? '');

                if ($sync_filter === 'on' && !$sync_enabled) {
                    continue;
                }
                if ($sync_filter === 'off' && $sync_enabled) {
                    continue;
                }
                if ($connected_filter === 'yes' && !$connected) {
                    continue;
                }
                if ($connected_filter === 'no' && $connected) {
                    continue;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower($name . ' ' . $email . ' ' . $calendar . ' ' . ($provider['id'] ?? ''));
                    if (!str_contains($haystack, $search)) {
                        continue;
                    }
                }

                $rows[] = [
                    'id' => (int) $provider['id'],
                    'name' => $name !== '' ? $name : ('#' . $provider['id']),
                    'email' => $email,
                    'sync_enabled' => $sync_enabled,
                    'connected' => $connected,
                    'google_calendar' => $calendar !== '' ? $calendar : 'primary',
                    'sync_past_days' => (int) ($settings['sync_past_days'] ?? 5),
                    'sync_future_days' => (int) ($settings['sync_future_days'] ?? 5),
                    'google_unavailability_count' => $this->count_google_unavailabilities((int) $provider['id']),
                ];
            }

            usort($rows, static function (array $a, array $b): int {
                return [$b['sync_enabled'], $b['connected'], $a['name']]
                    <=> [$a['sync_enabled'], $a['connected'], $b['name']];
            });

            json_response([
                'success' => true,
                'google_sync_feature' => filter_var(setting('google_sync_feature', '0'), FILTER_VALIDATE_BOOLEAN),
                'count' => count($rows),
                'providers' => $rows,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Return filtered Google-related log lines (JSON).
     */
    public function logs(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $date = trim((string) request('date', date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            $level = strtolower(trim((string) request('level', 'all')));
            $search = trim((string) request('search', ''));
            $limit = (int) request('limit', 200);
            $limit = max(20, min(1000, $limit));

            $available_dates = $this->list_log_dates();
            $lines = $this->read_google_log_lines($date, $level, $search, $limit);

            json_response([
                'success' => true,
                'date' => $date,
                'available_dates' => $available_dates,
                'count' => count($lines),
                'lines' => $lines,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete Google-sourced unavailabilities for a provider and re-run sync.
     *
     * Only removes EA unavailabilities that have an `id_google_calendar` (imported
     * busy blocks). Manual unavailabilities and bookings are left untouched.
     * Google Calendar events themselves are never deleted.
     */
    public function reset_unavailabilities(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            if (!filter_var(setting('google_sync_feature', '0'), FILTER_VALIDATE_BOOLEAN)) {
                abort(400, 'Google Calendar sync feature is disabled.');
            }

            $provider_id = (int) request('provider_id');

            if ($provider_id <= 0) {
                throw new InvalidArgumentException('Invalid provider ID provided.');
            }

            $provider = $this->providers_model->find($provider_id);
            $settings = $provider['settings'] ?? [];
            $sync_enabled = filter_var($settings['google_sync'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $token = trim((string) ($settings['google_token'] ?? ''));
            $connected = $token !== '' && $token !== 'null' && $token !== '[]' && $token !== '{}';

            if (!$sync_enabled || !$connected) {
                throw new RuntimeException(
                    'Provider must have Google sync enabled and be connected before resetting unavailabilities.',
                );
            }

            $deleted = $this->delete_google_unavailabilities($provider_id);

            log_message(
                'info',
                'Google - Reset Google-sourced unavailabilities for provider ID ' .
                    $provider_id .
                    ' (deleted ' .
                    $deleted .
                    '), re-syncing.',
            );

            // Prefer the extracted runner so this endpoint can return its own JSON payload.
            if (!class_exists('Google', false)) {
                require_once APPPATH . 'controllers/Google.php';
            }

            $sync_result = Google::run_sync((string) $provider_id);

            if ($sync_result === null) {
                throw new RuntimeException('Google sync could not be started for this provider.');
            }

            if (empty($sync_result['success'])) {
                json_response(
                    [
                        'success' => false,
                        'deleted' => $deleted,
                        'message' => $sync_result['message'] ?? 'Google sync failed after reset.',
                        'sync' => $sync_result,
                    ],
                    (int) ($sync_result['status'] ?? 400),
                );

                return;
            }

            json_response([
                'success' => true,
                'deleted' => $deleted,
                'message' => lang('google_unavailabilities_reset_success'),
                'warning' => $sync_result['warning'] ?? null,
                'sync' => $sync_result,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Count Google-sourced unavailabilities for a provider.
     */
    private function count_google_unavailabilities(int $provider_id): int
    {
        $rows = $this->unavailabilities_model->get([
            'id_users_provider' => $provider_id,
        ]);

        $count = 0;

        foreach ($rows as $row) {
            if (!empty($row['id_google_calendar'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete Google-sourced unavailabilities for a provider (no webhooks, no Google deletes).
     */
    private function delete_google_unavailabilities(int $provider_id): int
    {
        $rows = $this->unavailabilities_model->get([
            'id_users_provider' => $provider_id,
        ]);

        $deleted = 0;

        foreach ($rows as $row) {
            if (empty($row['id_google_calendar'])) {
                continue; // Keep manual EA unavailabilities.
            }

            $this->unavailabilities_model->delete((int) $row['id']);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function list_log_dates(): array
    {
        $files = glob(storage_path('logs/log-*.php')) ?: [];
        $dates = [];

        foreach ($files as $file) {
            if (preg_match('/log-(\d{4}-\d{2}-\d{2})\.php$/', basename($file), $matches)) {
                $dates[] = $matches[1];
            }
        }

        rsort($dates);

        return array_slice($dates, 0, 30);
    }

    /**
     * @return list<array{timestamp:?string,level:string,message:string}>
     */
    private function read_google_log_lines(string $date, string $level, string $search, int $limit): array
    {
        $path = storage_path('logs/log-' . $date . '.php');

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $raw = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($raw)) {
            return [];
        }

        $matched = [];
        $search_lower = mb_strtolower($search);

        foreach ($raw as $line) {
            $line = trim((string) $line);

            if ($line === '' || str_starts_with($line, '<?php')) {
                continue;
            }

            // Focus on Google sync related messages.
            if (
                stripos($line, 'Google') === false &&
                stripos($line, 'Synchronization') === false &&
                stripos($line, 'google_sync') === false
            ) {
                continue;
            }

            $parsed_level = 'info';
            if (preg_match('/^\s*(ERROR|DEBUG|INFO|WARNING)\s*-/i', $line, $level_match)) {
                $parsed_level = strtolower($level_match[1]);
            } elseif (stripos($line, 'error') !== false) {
                $parsed_level = 'error';
            }

            if ($level !== 'all' && $parsed_level !== $level) {
                continue;
            }

            if ($search_lower !== '' && !str_contains(mb_strtolower($line), $search_lower)) {
                continue;
            }

            $timestamp = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $time_match)) {
                $timestamp = $time_match[1];
            }

            $matched[] = [
                'timestamp' => $timestamp,
                'level' => $parsed_level,
                'message' => $line,
            ];
        }

        // Newest first.
        $matched = array_reverse($matched);

        return array_slice($matched, 0, $limit);
    }
}
