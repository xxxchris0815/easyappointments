<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * HTTP/DB integration tests for appointment creator ownership and webhook actions.
 *
 * Requires the local PHP app on BASE_URL and a reachable MySQL database.
 */
final class AppointmentCreatedByWebhookTest extends TestCase
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

    public static function setUpBeforeClass(): void
    {
        // Ensure a local webhook catcher is available.
        if (@file_get_contents('http://127.0.0.1:8099/') === false) {
            // Best-effort start; tests will skip if still unavailable.
            $cmd =
                'php -S 127.0.0.1:8099 -t /tmp >/tmp/webhook-catcher.log 2>&1 & echo $! > /tmp/webhook-catcher.pid';
            // Use a router script if present.
            if (!is_file('/tmp/webhook_catcher.php')) {
                file_put_contents(
                    '/tmp/webhook_catcher.php',
                    "<?php\nfile_put_contents('/tmp/webhook-hits.jsonl', date('c').' '.file_get_contents('php://input').\"\\n\", FILE_APPEND);\nhttp_response_code(200);\nheader('Content-Type: application/json');\necho '{\"ok\":true}';\n",
                );
            }
            exec('php -S 127.0.0.1:8099 /tmp/webhook_catcher.php >/tmp/webhook-catcher.log 2>&1 & echo $! > /tmp/webhook-catcher.pid');
            usleep(400000);
        }
    }

    protected function setUp(): void
    {
        $this->baseUrl = getenv('EA_BASE_URL') ?: 'http://127.0.0.1:8080/index.php';
        $this->cookieFile = sys_get_temp_dir() . '/ea_integration_cookies_' . getmypid() . '.txt';
        $this->webhookLog = '/tmp/webhook-hits.jsonl';

        if (!$this->isAppReachable()) {
            $this->markTestSkipped('Local Easy!Appointments app is not reachable at ' . $this->baseUrl);
        }

        if (!$this->isWebhookCatcherReachable()) {
            $this->markTestSkipped('Local webhook catcher is not reachable at http://127.0.0.1:8099/');
        }

        $this->pdo = $this->connectDb();
        $this->ensureSecretaryUser();
        $this->ensureWebhook();
        @unlink($this->webhookLog);
    }

    protected function tearDown(): void
    {
        if ($this->pdo) {
            foreach ($this->createdAppointmentIds as $appointmentId) {
                $stmt = $this->pdo->prepare('DELETE FROM ea_appointments WHERE id = ?');
                $stmt->execute([$appointmentId]);
            }

            if ($this->webhookId > 0) {
                $stmt = $this->pdo->prepare('DELETE FROM ea_webhooks WHERE id = ?');
                $stmt->execute([$this->webhookId]);
            }
        }

        @unlink($this->cookieFile);
    }

    public function testSecretaryCreateSetsCreatedByAndFiresCreateWebhook(): void
    {
        $this->login('secretary', 'secretary');

        $start = gmdate('Y-m-d H:i:s', strtotime('+3 days 10:00:00'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+3 days 10:30:00'));

        $response = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'integration secretary create',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);

        $this->assertTrue($response['success'] ?? false, json_encode($response));

        $row = $this->latestAppointmentByNotes('integration secretary create');
        $this->assertNotNull($row);
        $this->createdAppointmentIds[] = (int) $row['id'];

        $this->assertSame($this->secretaryId, (int) $row['id_users_created_by']);
        $this->assertArrayNotHasKey('id_users_secretary', $row);

        $actions = $this->webhookActionsForAppointment((int) $row['id']);
        $this->assertContains('appointment_create', $actions);
        $this->assertContains('appointment_save', $actions);
        $this->assertNotContains('appointment_update', $actions);

        $payload = $this->firstWebhookPayload('appointment_create', (int) $row['id']);
        $this->assertSame($this->secretaryId, (int) $payload['id_users_created_by']);
        $this->assertArrayNotHasKey('id_users_secretary', $payload);
    }

    public function testSecretaryUpdateFiresUpdateWebhookAndKeepsCreatedBy(): void
    {
        $this->login('secretary', 'secretary');

        $start = gmdate('Y-m-d H:i:s', strtotime('+4 days 10:00:00'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+4 days 10:30:00'));

        $create = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'integration secretary update target',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($create['success'] ?? false, json_encode($create));

        $row = $this->latestAppointmentByNotes('integration secretary update target');
        $this->assertNotNull($row);
        $appointmentId = (int) $row['id'];
        $this->createdAppointmentIds[] = $appointmentId;

        @unlink($this->webhookLog);

        $update = $this->saveAppointment([
            'id' => $appointmentId,
            'start_datetime' => gmdate('Y-m-d H:i:s', strtotime('+4 days 11:00:00')),
            'end_datetime' => gmdate('Y-m-d H:i:s', strtotime('+4 days 11:30:00')),
            'notes' => 'integration secretary update target',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($update['success'] ?? false, json_encode($update));

        $updated = $this->appointmentById($appointmentId);
        $this->assertSame($this->secretaryId, (int) $updated['id_users_created_by']);

        $actions = $this->webhookActionsForAppointment($appointmentId);
        $this->assertContains('appointment_update', $actions);
        $this->assertContains('appointment_save', $actions);
        $this->assertNotContains('appointment_create', $actions);
    }

    public function testProviderCreateSetsCreatedByToProvider(): void
    {
        $this->ensureProviderPassword();
        $this->login('janedoe', 'provider');

        $start = gmdate('Y-m-d H:i:s', strtotime('+5 days 10:00:00'));
        $end = gmdate('Y-m-d H:i:s', strtotime('+5 days 10:30:00'));

        $response = $this->saveAppointment([
            'start_datetime' => $start,
            'end_datetime' => $end,
            'notes' => 'integration provider create',
            'id_users_provider' => $this->providerId,
            'id_users_customer' => $this->customerId,
            'id_services' => $this->serviceId,
            'status' => 'Booked',
        ]);
        $this->assertTrue($response['success'] ?? false, json_encode($response));

        $row = $this->latestAppointmentByNotes('integration provider create');
        $this->assertNotNull($row);
        $this->createdAppointmentIds[] = (int) $row['id'];

        $this->assertSame($this->providerId, (int) $row['id_users_created_by']);

        $payload = $this->firstWebhookPayload('appointment_create', (int) $row['id']);
        $this->assertSame($this->providerId, (int) $payload['id_users_created_by']);
    }

    private function isAppReachable(): bool
    {
        $body = @file_get_contents($this->baseUrl . '/login');
        return $body !== false;
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

    private function connectDb(): PDO
    {
        require_once dirname(__DIR__, 2) . '/config.php';

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', \Config::DB_HOST, \Config::DB_NAME);

        return new PDO($dsn, \Config::DB_USERNAME, \Config::DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function ensureSecretaryUser(): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id FROM ea_users u JOIN ea_roles r ON r.id = u.id_roles WHERE r.slug = 'secretary' LIMIT 1",
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if ($id) {
            $this->secretaryId = (int) $id;
        } else {
            $hash = password_hash('secretary', PASSWORD_BCRYPT, ['cost' => 12]);
            $salt = substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 20);

            $this->pdo
                ->prepare(
                    'INSERT INTO ea_users (create_datetime, update_datetime, first_name, last_name, email, phone_number, id_roles)
                     VALUES (NOW(), NOW(), ?, ?, ?, ?, (SELECT id FROM ea_roles WHERE slug = ? LIMIT 1))',
                )
                ->execute(['Sara', 'Secretary', 'sara.secretary@example.org', '000', 'secretary']);

            $this->secretaryId = (int) $this->pdo->lastInsertId();

            $this->pdo
                ->prepare(
                    'INSERT INTO ea_user_settings (id_users, username, password, salt, notifications, calendar_view)
                     VALUES (?, ?, ?, ?, 1, ?)',
                )
                ->execute([$this->secretaryId, 'secretary', $hash, $salt, 'default']);
        }

        // Ensure password is known for tests.
        $hash = password_hash('secretary', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo
            ->prepare('UPDATE ea_user_settings SET password = ? WHERE id_users = ?')
            ->execute([$hash, $this->secretaryId]);

        $this->pdo->prepare('DELETE FROM ea_secretaries_providers WHERE id_users_secretary = ?')->execute([
            $this->secretaryId,
        ]);
        $this->pdo
            ->prepare('INSERT INTO ea_secretaries_providers (id_users_secretary, id_users_provider) VALUES (?, ?)')
            ->execute([$this->secretaryId, $this->providerId]);
    }

    private function ensureProviderPassword(): void
    {
        $hash = password_hash('provider', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo
            ->prepare('UPDATE ea_user_settings SET password = ? WHERE username = ?')
            ->execute([$hash, 'janedoe']);
    }

    private function ensureWebhook(): void
    {
        $this->pdo->prepare("DELETE FROM ea_webhooks WHERE name = 'integration-appointment-hooks'")->execute();
        $this->pdo
            ->prepare(
                'INSERT INTO ea_webhooks (create_datetime, update_datetime, name, url, actions, secret_token, is_ssl_verified, notes)
                 VALUES (NOW(), NOW(), ?, ?, ?, ?, 0, ?)',
            )
            ->execute([
                'integration-appointment-hooks',
                'http://127.0.0.1:8099/',
                'appointment_save,appointment_create,appointment_update,appointment_delete',
                '',
                'integration test',
            ]);
        $this->webhookId = (int) $this->pdo->lastInsertId();
    }

    private function login(string $username, string $password): void
    {
        @unlink($this->cookieFile);

        $loginPage = $this->request('GET', '/login');
        if (!preg_match('/csrf_token":"([^"]+)"/', $loginPage, $match)) {
            $this->fail('Could not extract CSRF token from login page.');
        }

        $token = $match[1];
        $body = $this->request('POST', '/login/validate', [
            'csrf_token' => $token,
            'username' => $username,
            'password' => $password,
        ]);

        $json = json_decode($body, true);
        $this->assertTrue($json['success'] ?? false, 'Login failed for ' . $username . ': ' . $body);
    }

    private function saveAppointment(array $appointment): array
    {
        $csrf = $this->currentCsrfToken();

        $fields = [
            'csrf_token' => $csrf,
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
        if (!is_file($this->cookieFile)) {
            $this->fail('Cookie file missing; login first.');
        }

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
        if ($pos === false) {
            return $body;
        }

        return substr($body, $pos);
    }

    private function latestAppointmentByNotes(string $notes): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ea_appointments WHERE notes = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute([$notes]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function appointmentById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ea_appointments WHERE id = ?');
        $stmt->execute([$id]);
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
            if ((int) ($data['payload']['id'] ?? 0) !== $appointmentId) {
                continue;
            }
            $actions[] = $data['action'] ?? '';
        }

        return $actions;
    }

    private function firstWebhookPayload(string $action, int $appointmentId): array
    {
        if (!is_file($this->webhookLog)) {
            $this->fail('Webhook log missing.');
        }

        foreach (file($this->webhookLog) as $line) {
            $raw = trim(substr($line, strpos($line, '{') ?: 0));
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            if (($data['action'] ?? '') !== $action) {
                continue;
            }
            if ((int) ($data['payload']['id'] ?? 0) !== $appointmentId) {
                continue;
            }

            return $data['payload'];
        }

        $this->fail('Webhook payload not found for action ' . $action . ' appointment ' . $appointmentId);
    }
}
