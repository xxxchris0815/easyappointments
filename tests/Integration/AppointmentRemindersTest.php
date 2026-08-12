<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for appointment reminder scheduling and delivery.
 */
final class AppointmentRemindersTest extends TestCase
{
    private string $baseUrl;
    private string $cookieFile;
    private string $webhookLog;
    private ?PDO $pdo = null;
    private int $secretaryId = 0;
    private int $providerId = 2;
    private int $customerId = 4;
    private int $serviceId = 1;
    private int $webhookId = 0;
    private array $createdAppointmentIds = [];

    protected function setUp(): void
    {
        $this->baseUrl = getenv('EA_BASE_URL') ?: 'http://127.0.0.1:8080/index.php';
        $this->cookieFile = sys_get_temp_dir() . '/ea_reminder_cookies_' . getmypid() . '.txt';
        $this->webhookLog = '/tmp/webhook-hits.jsonl';

        if (!$this->isAppReachable()) {
            $this->markTestSkipped('Local Easy!Appointments app is not reachable at ' . $this->baseUrl);
        }

        if (!$this->isWebhookCatcherReachable()) {
            $this->markTestSkipped('Local webhook catcher is not reachable at http://127.0.0.1:8099/');
        }

        $this->pdo = $this->connectDb();
        $this->ensureSecretaryUser();
        $this->ensureReminderSettings();
        $this->ensureWebhook();
        @unlink($this->webhookLog);
    }

    protected function tearDown(): void
    {
        if ($this->pdo) {
            foreach ($this->createdAppointmentIds as $appointmentId) {
                $this->pdo->prepare('DELETE FROM ea_appointment_reminder_deliveries WHERE id_appointments = ?')->execute([
                    $appointmentId,
                ]);
                $this->pdo->prepare('DELETE FROM ea_appointments WHERE id = ?')->execute([$appointmentId]);
            }

            if ($this->webhookId > 0) {
                $this->pdo->prepare('DELETE FROM ea_webhooks WHERE id = ?')->execute([$this->webhookId]);
            }
        }

        @unlink($this->cookieFile);
    }

    public function testMultipleReminderRulesAreScheduledOnCreate(): void
    {
        $this->login('secretary', 'secretary');

        $start = gmdate('Y-m-d H:i:s', strtotime('+3 days 12:00:00'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+3 days 12:30:00'));

        $response = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'reminder multi-rule target',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($response['success'] ?? false, json_encode($response));

        $appointment = $this->latestAppointmentByNotes('reminder multi-rule target');
        $this->assertNotNull($appointment);
        $appointmentId = (int) $appointment['id'];
        $this->createdAppointmentIds[] = $appointmentId;

        $stmt = $this->pdo->prepare(
            'SELECT reminder_key, channel, status FROM ea_appointment_reminder_deliveries WHERE id_appointments = ? ORDER BY reminder_key, channel',
        );
        $stmt->execute([$appointmentId]);
        $rows = $stmt->fetchAll();

        $this->assertCount(3, $rows, 'Expected 3 deliveries: r1/email, r1/webhook, r2/email');
        $this->assertSame(
            [
                ['reminder_key' => 'r1', 'channel' => 'email', 'status' => 'pending'],
                ['reminder_key' => 'r1', 'channel' => 'webhook', 'status' => 'pending'],
                ['reminder_key' => 'r2', 'channel' => 'email', 'status' => 'pending'],
            ],
            $rows,
        );
    }

    public function testConsoleWorkerSendsDueWebhookReminderOnce(): void
    {
        $this->login('secretary', 'secretary');

        $start = gmdate('Y-m-d H:i:s', strtotime('+4 hours'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+4 hours +30 minutes'));

        $response = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'reminder worker target',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($response['success'] ?? false, json_encode($response));

        $appointment = $this->latestAppointmentByNotes('reminder worker target');
        $this->assertNotNull($appointment);
        $appointmentId = (int) $appointment['id'];
        $this->createdAppointmentIds[] = $appointmentId;

        $delivery = $this->pdo->prepare(
            "SELECT id, status FROM ea_appointment_reminder_deliveries WHERE id_appointments=? AND reminder_key='r1' AND channel='webhook'",
        );
        $delivery->execute([$appointmentId]);
        $row = $delivery->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('pending', $row['status']);

        $this->pdo
            ->prepare('UPDATE ea_appointment_reminder_deliveries SET due_datetime=? WHERE id=?')
            ->execute([gmdate('Y-m-d H:i:s', strtotime('-2 minutes')), $row['id']]);

        @unlink($this->webhookLog);
        exec('cd /workspace && php index.php console reminders', $output1, $code1);
        $this->assertSame(0, $code1, implode("\n", $output1));

        $delivery->execute([$appointmentId]);
        $afterFirst = $delivery->fetch();
        $this->assertSame('sent', $afterFirst['status']);

        $actions = $this->webhookActionsForAppointment($appointmentId);
        $this->assertContains('appointment_reminder', $actions);
        $this->assertSame(1, count(array_filter($actions, static fn($a) => $a === 'appointment_reminder')));

        // Second run must not resend.
        @unlink($this->webhookLog);
        exec('cd /workspace && php index.php console reminders', $output2, $code2);
        $this->assertSame(0, $code2, implode("\n", $output2));
        $this->assertSame([], $this->webhookActionsForAppointment($appointmentId));
    }

    public function testCancelledAppointmentSkipsPendingReminders(): void
    {
        $this->login('secretary', 'secretary');

        $start = gmdate('Y-m-d H:i:s', strtotime('+5 hours'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+5 hours +30 minutes'));

        $response = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'reminder cancel skip target',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($response['success'] ?? false, json_encode($response));

        $appointment = $this->latestAppointmentByNotes('reminder cancel skip target');
        $this->assertNotNull($appointment);
        $appointmentId = (int) $appointment['id'];
        $this->createdAppointmentIds[] = $appointmentId;

        $pendingBefore = $this->pdo
            ->prepare('SELECT COUNT(*) FROM ea_appointment_reminder_deliveries WHERE id_appointments=? AND status=?');
        $pendingBefore->execute([$appointmentId, 'pending']);
        $this->assertGreaterThan(0, (int) $pendingBefore->fetchColumn());

        $csrf = $this->currentCsrfToken();
        $body = $this->request('POST', '/calendar/delete_appointment', [
            'csrf_token' => $csrf,
            'appointment_id' => (string) $appointmentId,
            'cancellation_reason' => 'skip reminders',
            'notify_users' => '0',
        ]);
        $json = json_decode($this->extractJson($body), true);
        $this->assertTrue($json['success'] ?? false, $body);

        $pendingAfter = $this->pdo
            ->prepare('SELECT COUNT(*) FROM ea_appointment_reminder_deliveries WHERE id_appointments=? AND status=?');
        $pendingAfter->execute([$appointmentId, 'pending']);
        $this->assertSame(0, (int) $pendingAfter->fetchColumn());

        $skipped = $this->pdo
            ->prepare('SELECT COUNT(*) FROM ea_appointment_reminder_deliveries WHERE id_appointments=? AND status=?');
        $skipped->execute([$appointmentId, 'skipped']);
        $this->assertGreaterThan(0, (int) $skipped->fetchColumn());

        // Even if due, worker must not send for cancelled appointments.
        $this->pdo
            ->prepare(
                "UPDATE ea_appointment_reminder_deliveries
                 SET status='pending', due_datetime=?, sent_at=NULL
                 WHERE id_appointments=? AND channel='webhook'",
            )
            ->execute([gmdate('Y-m-d H:i:s', strtotime('-1 minute')), $appointmentId]);

        @unlink($this->webhookLog);
        exec('cd /workspace && php index.php console reminders', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertNotContains('appointment_reminder', $this->webhookActionsForAppointment($appointmentId));
    }

    private function ensureReminderSettings(): void
    {
        $this->pdo->exec("UPDATE ea_settings SET value='1' WHERE name='appointment_reminders_enabled'");
        $this->pdo
            ->prepare('UPDATE ea_settings SET value=? WHERE name=\'appointment_reminders\'')
            ->execute([
                json_encode([
                    [
                        'id' => 'r1',
                        'offset' => 60,
                        'unit' => 'minutes',
                        'channels' => ['email', 'webhook'],
                    ],
                    [
                        'id' => 'r2',
                        'offset' => 24,
                        'unit' => 'hours',
                        'channels' => ['email'],
                    ],
                ]),
            ]);
    }

    private function ensureWebhook(): void
    {
        $this->pdo->prepare("DELETE FROM ea_webhooks WHERE name = 'integration-reminder-hooks'")->execute();
        $this->pdo
            ->prepare(
                'INSERT INTO ea_webhooks (create_datetime, update_datetime, name, url, actions, secret_token, is_ssl_verified, notes)
                 VALUES (NOW(), NOW(), ?, ?, ?, ?, 0, ?)',
            )
            ->execute([
                'integration-reminder-hooks',
                'http://127.0.0.1:8099/',
                'appointment_reminder,appointment_delete,appointment_create,appointment_save',
                '',
                'reminder integration',
            ]);
        $this->webhookId = (int) $this->pdo->lastInsertId();
    }

    private function ensureSecretaryUser(): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id FROM ea_users u JOIN ea_roles r ON r.id = u.id_roles WHERE r.slug = 'secretary' LIMIT 1",
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if (!$id) {
            $this->markTestSkipped('Secretary user is required for reminder integration tests.');
        }

        $this->secretaryId = (int) $id;
        $hash = password_hash('secretary', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo
            ->prepare('UPDATE ea_user_settings SET password = ?, username = ? WHERE id_users = ?')
            ->execute([$hash, 'secretary', $this->secretaryId]);

        $this->pdo->prepare('DELETE FROM ea_secretaries_providers WHERE id_users_secretary = ?')->execute([
            $this->secretaryId,
        ]);
        $this->pdo
            ->prepare('INSERT INTO ea_secretaries_providers (id_users_secretary, id_users_provider) VALUES (?, ?)')
            ->execute([$this->secretaryId, $this->providerId]);
    }

    private function connectDb(): PDO
    {
        require_once dirname(__DIR__, 2) . '/config.php';
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', \Config::DB_HOST, \Config::DB_NAME);

        return new PDO($dsn, \Config::DB_USERNAME, \Config::DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function isAppReachable(): bool
    {
        return @file_get_contents($this->baseUrl . '/login') !== false;
    }

    private function isWebhookCatcherReachable(): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => '{"ping":true}',
                'timeout' => 2,
            ],
        ]);

        return @file_get_contents('http://127.0.0.1:8099/', false, $context) !== false;
    }

    private function login(string $username, string $password): void
    {
        @unlink($this->cookieFile);
        $loginPage = $this->request('GET', '/login');
        if (!preg_match('/csrf_token":"([^"]+)"/', $loginPage, $match)) {
            $this->fail('Could not extract CSRF token from login page.');
        }

        $body = $this->request('POST', '/login/validate', [
            'csrf_token' => $match[1],
            'username' => $username,
            'password' => $password,
        ]);
        $json = json_decode($body, true);
        $this->assertTrue($json['success'] ?? false, 'Login failed for ' . $username . ': ' . $body);
    }

    private function saveAppointment(array $appointment): array
    {
        $fields = [
            'csrf_token' => $this->currentCsrfToken(),
            'force_save' => '1',
            'notify_users' => '0',
        ];

        foreach ($appointment as $key => $value) {
            $fields['appointment_data[' . $key . ']'] = (string) $value;
        }

        $body = $this->request('POST', '/calendar/save_appointment', $fields);
        $json = json_decode($this->extractJson($body), true);

        return is_array($json) ? $json : ['success' => false, 'raw' => $body];
    }

    private function currentCsrfToken(): string
    {
        foreach (file($this->cookieFile) as $line) {
            if (str_contains($line, 'csrf_cookie')) {
                $parts = preg_split('/\s+/', trim($line));
                return end($parts);
            }
        }

        $this->fail('csrf_cookie not found after login.');
    }

    private function request(string $method, string $path, array $fields = []): string
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $this->fail('HTTP request failed: ' . curl_error($ch));
        }
        curl_close($ch);

        return $body;
    }

    private function extractJson(string $body): string
    {
        $pos = strrpos($body, '{');
        return $pos === false ? $body : substr($body, $pos);
    }

    private function latestAppointmentByNotes(string $notes): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ea_appointments WHERE notes = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$notes]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function webhookActionsForAppointment(int $appointmentId): array
    {
        if (!is_file($this->webhookLog)) {
            return [];
        }

        $actions = [];
        foreach (file($this->webhookLog) as $line) {
            $raw = trim(substr($line, strpos($line, '{') ?: 0));
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }

            $payload = $data['payload'] ?? [];
            $payloadAppointmentId = (int) ($payload['id'] ?? ($payload['appointment']['id'] ?? 0));
            if ($payloadAppointmentId !== $appointmentId) {
                continue;
            }

            $actions[] = $data['action'] ?? '';
        }

        return $actions;
    }
}
