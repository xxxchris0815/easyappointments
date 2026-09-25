<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

use Jsvrcek\ICS\Exception\CalendarEventException;

require_once __DIR__ . '/Google.php';
require_once __DIR__ . '/Caldav.php';

/**
 * Console controller.
 *
 * Handles all the Console related operations.
 */
class Console extends EA_Controller
{
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();

        $this->load->library('instance');
        $this->load->library('cleanup');

        $this->load->model('admins_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     *
     * @throws Exception
     */
    public function install(): void
    {
        $this->instance->migrate('fresh');

        $password = $this->instance->seed();

        response(
            PHP_EOL . '⇾ Installation completed, login with "administrator" / "' . $password . '".' . PHP_EOL . PHP_EOL,
        );
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an Easy!Appointments instance to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc.).
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate(string $type = ''): void
    {
        $this->instance->migrate($type);
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     * @throws Exception
     */
    public function seed(): void
    {
        $this->instance->seed();
    }

    /**
     * Create a database backup file.
     *
     * Use this method to back up your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup(): void
    {
        $this->instance->backup($GLOBALS['argv'][3] ?? null);
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     *
     * @throws CalendarEventException
     * @throws Exception
     * @throws Throwable
     */
    public function sync(): void
    {
        $providers = $this->providers_model->get();

        foreach ($providers as $provider) {
            if (filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Google::sync((string) $provider['id']);
            }

            if (filter_var($provider['settings']['caldav_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Caldav::sync((string) $provider['id']);
            }
        }
    }

    /**
     * Clean up old customer data based on data retention settings.
     *
     * Use this method in a cronjob to automatically delete customer data older than the configured retention period.
     *
     * Usage:
     *
     * php index.php console cleanup
     *
     * @throws Exception
     */
    public function cleanup(): void
    {
        $this->cleanup->run();
    }

    /**
     * Process due appointment reminders (email/webhook).
     *
     * Usage:
     *
     * php index.php console reminders
     */
    public function reminders(): void
    {
        $this->load->library('reminders');

        $stats = $this->reminders->run();

        response(
            PHP_EOL .
                '⇾ Reminders processed=' .
                $stats['processed'] .
                ' sent=' .
                $stats['sent'] .
                ' failed=' .
                $stats['failed'] .
                ' skipped=' .
                $stats['skipped'] .
                PHP_EOL .
                PHP_EOL,
        );
    }

    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help(): void
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console migrate up',
            '⇾ php index.php console migrate down',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console sync',
            '⇾ php index.php console cleanup    (cleans sessions, logs, cache, and customer data)',
            '⇾ php index.php console reminders (sends due appointment reminder emails/webhooks)',
            '⇾ php index.php console any_provider_test (live check of any-provider assignment modes)',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }

    /**
     * Live-check Any Provider assignment modes against the current database.
     *
     * Usage:
     *
     * php index.php console any_provider_test
     */
    public function any_provider_test(): void
    {
        $this->load->library('any_provider_assignment');

        $service_id = 1;
        $date = (new DateTime('monday'))->format('Y-m-d');
        $hour = '10:00';

        $out = [];
        $out[] = "Date under test: {$date} {$hour}";

        $fail = static function (string $msg) use (&$out): void {
            $out[] = "FAIL: {$msg}";
            response(implode(PHP_EOL, $out) . PHP_EOL);
            exit(1);
        };

        $ok = static function (string $msg) use (&$out): void {
            $out[] = "OK: {$msg}";
        };

        $set_mode = static function (string $mode): void {
            setting(['any_provider_selection_mode' => $mode]);
            setting(['any_provider_rr_counter' => '0']);
        };

        $candidates = $this->any_provider_assignment->collect_candidates($service_id, $date, $hour);
        $out[] = 'Candidates: ' . json_encode($candidates);

        if (count($candidates) < 2) {
            $fail('need at least two providers that can take the slot (service 1)');
        }

        $set_mode(ANY_PROVIDER_MODE_MOST_AVAILABLE);
        $picked = [];
        for ($i = 0; $i < 3; $i++) {
            $picked[] = $this->any_provider_assignment->select($service_id, $date, $hour);
        }
        $max_hours = max(array_column($candidates, 'available_hours_count'));
        $expected_most = null;
        foreach ($candidates as $candidate) {
            if ($candidate['available_hours_count'] === $max_hours) {
                $expected_most = $candidate['id'];
                break;
            }
        }
        if (count(array_unique($picked)) !== 1) {
            $fail('most_available should be stable, got ' . json_encode($picked));
        }
        if ($picked[0] !== $expected_most) {
            $fail("most_available picked {$picked[0]}, expected {$expected_most}");
        }
        $ok("most_available → provider {$picked[0]}");

        $set_mode(ANY_PROVIDER_MODE_ROUND_ROBIN);
        $picked = [];
        for ($i = 0; $i < count($candidates) * 2; $i++) {
            $picked[] = $this->any_provider_assignment->select($service_id, $date, $hour);
        }
        $expected_rr = [];
        for ($i = 0; $i < count($candidates) * 2; $i++) {
            $expected_rr[] = $candidates[$i % count($candidates)]['id'];
        }
        if ($picked !== $expected_rr) {
            $fail('round_robin expected ' . json_encode($expected_rr) . ' got ' . json_encode($picked));
        }
        $ok('round_robin rotates ' . json_encode($picked));

        $set_mode(ANY_PROVIDER_MODE_WEIGHTED_ROUND_ROBIN);
        $sequence = [];
        foreach ($candidates as $candidate) {
            for ($w = 0; $w < max(1, (int) $candidate['weight']); $w++) {
                $sequence[] = $candidate['id'];
            }
        }
        $picked = [];
        for ($i = 0; $i < count($sequence) * 2; $i++) {
            $picked[] = $this->any_provider_assignment->select($service_id, $date, $hour);
        }
        $expected_w = [];
        for ($i = 0; $i < count($sequence) * 2; $i++) {
            $expected_w[] = $sequence[$i % count($sequence)];
        }
        if ($picked !== $expected_w) {
            $fail(
                'weighted RR expected ' .
                    json_encode($expected_w) .
                    ' got ' .
                    json_encode($picked) .
                    ' seq=' .
                    json_encode($sequence),
            );
        }
        $ok('weighted_round_robin follows ' . json_encode($sequence));

        $counts = array_count_values(array_slice($picked, 0, count($sequence)));
        foreach ($candidates as $candidate) {
            $expected_count = max(1, (int) $candidate['weight']);
            $actual = $counts[$candidate['id']] ?? 0;
            if ($actual !== $expected_count) {
                $fail("provider {$candidate['id']} weight {$expected_count} but cycle count {$actual}");
            }
            $ok("provider {$candidate['id']} weight {$expected_count} → {$actual}x in cycle");
        }

        $set_mode(ANY_PROVIDER_MODE_MOST_AVAILABLE);
        $out[] = 'ALL LIVE CHECKS PASSED';
        response(implode(PHP_EOL, $out) . PHP_EOL);
    }
}
