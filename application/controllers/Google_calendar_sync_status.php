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

            // Prefer the extracted runner so this endpoint can return its own JSON payload.
            if (!class_exists('Google', false)) {
                require_once APPPATH . 'controllers/Google.php';
            }

            $this->load->library('google_sync');

            $google_token = json_decode($provider['settings']['google_token'] ?? '', true);

            if (empty($google_token['refresh_token'])) {
                throw new RuntimeException('Provider Google token is missing.');
            }

            $this->google_sync->refresh_token($google_token['refresh_token']);

            // Remove leftover "Unavailable" blockers that old EA sync pushed to Google.
            // Re-importing those is what recreates duplicate Nichtverfügbarkeit rows.
            $google_cleanup = $this->google_sync->remove_unavailable_events($provider);

            $deleted = $this->delete_google_unavailabilities($provider_id);

            log_message(
                'error',
                'Google Sync Audit - Reset provider ' .
                    $provider_id .
                    ' local_deleted=' .
                    $deleted .
                    ' google_unavailable_deleted=' .
                    ($google_cleanup['deleted'] ?? 0) .
                    ' google_unavailable_scanned=' .
                    ($google_cleanup['scanned'] ?? 0),
            );

            $sync_result = Google::run_sync((string) $provider_id);

            if ($sync_result === null) {
                throw new RuntimeException('Google sync could not be started for this provider.');
            }

            if (empty($sync_result['success'])) {
                json_response(
                    [
                        'success' => false,
                        'deleted' => $deleted,
                        'google_unavailable_deleted' => (int) ($google_cleanup['deleted'] ?? 0),
                        'google_unavailable_scanned' => (int) ($google_cleanup['scanned'] ?? 0),
                        'message' => $sync_result['message'] ?? 'Google sync failed after reset.',
                        'sync' => $sync_result,
                    ],
                    (int) ($sync_result['status'] ?? 400),
                );

                return;
            }

            $remaining = $this->count_google_unavailabilities($provider_id);

            json_response([
                'success' => true,
                'deleted' => $deleted,
                'google_unavailable_deleted' => (int) ($google_cleanup['deleted'] ?? 0),
                'google_unavailable_scanned' => (int) ($google_cleanup['scanned'] ?? 0),
                'collapsed_unavailabilities' => (int) ($sync_result['collapsed_unavailabilities'] ?? 0),
                'remaining_google_unavailabilities' => $remaining,
                'stats' => $sync_result['stats'] ?? null,
                'message' => lang('google_unavailabilities_reset_success'),
                'warning' => $sync_result['warning'] ?? null,
                'sync' => $sync_result,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Diagnostic dump of unavailabilities (for debugging duplicate busy blocks).
     */
    public function diagnose(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $provider_id = (int) request('provider_id');
            $days = (int) request('days', 14);
            $days = max(1, min(60, $days));

            $window_start = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));
            $window_end = date('Y-m-d 23:59:59', strtotime('+' . $days . ' days'));

            $providers = $this->providers_model->get();
            $provider_names = [];

            foreach ($providers as $provider) {
                $name = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
                $provider_names[(int) $provider['id']] = $name !== '' ? $name : ('#' . $provider['id']);
            }

            $where = [
                'start_datetime <' => $window_end,
                'end_datetime >' => $window_start,
            ];

            if ($provider_id > 0) {
                $where['id_users_provider'] = $provider_id;
            }

            $rows = $this->unavailabilities_model->get($where);
            $export = [];
            $by_slot = [];
            $by_google_id = [];
            $providers_in_result = [];

            foreach ($rows as $row) {
                $pid = (int) ($row['id_users_provider'] ?? 0);
                $providers_in_result[$pid] = true;
                $google_id = $row['id_google_calendar'] ?? null;
                $slot_key = $pid . '|' . ($row['start_datetime'] ?? '') . '|' . ($row['end_datetime'] ?? '');

                $item = [
                    'id' => (int) ($row['id'] ?? 0),
                    'provider_id' => $pid,
                    'provider_name' => $provider_names[$pid] ?? ('#' . $pid),
                    'start_datetime' => $row['start_datetime'] ?? null,
                    'end_datetime' => $row['end_datetime'] ?? null,
                    'id_google_calendar' => $google_id,
                    'id_caldav_calendar' => $row['id_caldav_calendar'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'create_datetime' => $row['create_datetime'] ?? null,
                    'source' => !empty($google_id)
                        ? 'google'
                        : (!empty($row['id_caldav_calendar']) ? 'caldav' : 'manual'),
                ];

                $export[] = $item;
                $by_slot[$slot_key][] = $item['id'];

                if (!empty($google_id)) {
                    $gid_key = $pid . '|' . $google_id;
                    $by_google_id[$gid_key][] = $item['id'];
                }
            }

            $duplicate_slots = [];

            foreach ($by_slot as $slot_key => $ids) {
                if (count($ids) < 2) {
                    continue;
                }

                [$pid, $start, $end] = explode('|', $slot_key, 3);
                $duplicate_slots[] = [
                    'provider_id' => (int) $pid,
                    'provider_name' => $provider_names[(int) $pid] ?? ('#' . $pid),
                    'start_datetime' => $start,
                    'end_datetime' => $end,
                    'count' => count($ids),
                    'ids' => $ids,
                ];
            }

            $duplicate_google_ids = [];

            foreach ($by_google_id as $gid_key => $ids) {
                if (count($ids) < 2) {
                    continue;
                }

                [$pid, $google_id] = explode('|', $gid_key, 2);
                $duplicate_google_ids[] = [
                    'provider_id' => (int) $pid,
                    'provider_name' => $provider_names[(int) $pid] ?? ('#' . $pid),
                    'id_google_calendar' => $google_id,
                    'count' => count($ids),
                    'ids' => $ids,
                ];
            }

            usort($duplicate_slots, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
            usort($duplicate_google_ids, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

            json_response([
                'success' => true,
                'generated_at' => date('c'),
                'provider_id' => $provider_id > 0 ? $provider_id : null,
                'window_start' => $window_start,
                'window_end' => $window_end,
                'distinct_providers' => count($providers_in_result),
                'total_unavailabilities' => count($export),
                'google_sourced' => count(array_filter($export, static fn(array $r): bool => $r['source'] === 'google')),
                'manual' => count(array_filter($export, static fn(array $r): bool => $r['source'] === 'manual')),
                'caldav_sourced' => count(array_filter($export, static fn(array $r): bool => $r['source'] === 'caldav')),
                'duplicate_slot_groups' => $duplicate_slots,
                'duplicate_google_id_groups' => $duplicate_google_ids,
                'unavailabilities' => $export,
                'hint' =>
                    'If distinct_providers > 1 and the calendar filter is "All"/service, side-by-side blocks can be different providers. Same provider_id with duplicate_slot_groups points to true sync duplicates.',
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
