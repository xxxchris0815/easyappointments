<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Smoke checks for user welcome email support.
 */
class UserWelcomeEmailSmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testAccountsExposesWelcomeEmailHelpers(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Accounts.php');

        $this->assertStringContainsString('function send_welcome_email', $source);
        $this->assertStringContainsString('function generate_reset_token_for_user_id', $source);
        $this->assertStringContainsString("'+7 days'", $source);
    }

    public function testEmailMessagesSupportsWelcomeMail(): void
    {
        $source = file_get_contents($this->root . '/application/libraries/Email_messages.php');

        $this->assertStringContainsString('function send_user_welcome', $source);
        $this->assertStringContainsString('user_welcome_email_subject', $source);
    }

    public function testCreateControllersSendWelcomeEmail(): void
    {
        foreach (['Providers.php', 'Secretaries.php', 'Admins.php'] as $file) {
            $source = file_get_contents($this->root . '/application/controllers/' . $file);
            $this->assertStringContainsString('send_welcome_email', $source, $file);
        }

        foreach (['Providers_api_v1.php', 'Secretaries_api_v1.php', 'Admins_api_v1.php'] as $file) {
            $source = file_get_contents(
                $this->root . '/application/controllers/api/v1/' . $file,
            );
            $this->assertStringContainsString('send_welcome_email', $source, $file);
        }
    }

    public function testWelcomeTranslationsExist(): void
    {
        foreach (['english', 'german'] as $locale) {
            $source = file_get_contents(
                $this->root . '/application/language/' . $locale . '/translations_lang.php',
            );
            $this->assertStringContainsString("\$lang['user_welcome_email_subject']", $source, $locale);
            $this->assertStringContainsString("\$lang['user_welcome_set_password']", $source, $locale);
            $this->assertStringContainsString("\$lang['secretary']", $source, $locale);
            $this->assertStringContainsString("\$lang['admin']", $source, $locale);
        }
    }
}
