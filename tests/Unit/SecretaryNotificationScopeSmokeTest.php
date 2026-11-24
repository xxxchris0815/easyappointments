<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Secretaries must only be notified for appointments they created.
 */
class SecretaryNotificationScopeSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSecretaryNotifyRequiresCreatedByMatch(): void
    {
        $notifications = file_get_contents($this->root . '/application/libraries/Notifications.php');
        $view = file_get_contents($this->root . '/application/views/pages/secretaries.php');
        $de = file_get_contents($this->root . '/application/language/german/translations_lang.php');

        $this->assertStringContainsString('function secretary_owns_appointment', $notifications);
        $this->assertStringContainsString('id_users_created_by', $notifications);
        $this->assertStringContainsString('skip_secretary_not_creator', $notifications);
        $this->assertStringContainsString('secretary_notifications_hint', $view);
        $this->assertStringContainsString('secretary_notifications_hint', $de);
    }
}
