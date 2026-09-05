<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pure logic checks for booking success redirect URL templates.
 */
final class BookingSuccessRedirectLogicTest extends TestCase
{
    private function isValidTemplate(string $template): bool
    {
        $template = trim($template);

        if ($template === '') {
            return true;
        }

        $probe = preg_replace('/\{[a-z0-9_]+\}/i', 'placeholder', $template);
        $parts = parse_url((string) $probe);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
    }

    private function buildUrl(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($values): string {
                $key = strtolower($matches[1]);

                if (!array_key_exists($key, $values)) {
                    return '';
                }

                return rawurlencode((string) $values[$key]);
            },
            $template,
        );
    }

    public function testEmptyTemplateIsValid(): void
    {
        $this->assertTrue($this->isValidTemplate(''));
        $this->assertTrue($this->isValidTemplate('   '));
    }

    public function testAbsoluteHttpTemplateWithPlaceholdersIsValid(): void
    {
        $this->assertTrue(
            $this->isValidTemplate('https://example.com/danke?datum={date}&zeit={time}&name={customer_name}'),
        );
    }

    public function testRelativeOrJavascriptTemplatesAreInvalid(): void
    {
        $this->assertFalse($this->isValidTemplate('/danke'));
        $this->assertFalse($this->isValidTemplate('javascript:alert(1)'));
        $this->assertFalse($this->isValidTemplate('ftp://example.com/x'));
    }

    public function testPlaceholdersAreUrlEncoded(): void
    {
        $url = $this->buildUrl(
            'https://example.com/thanks?date={date}&name={customer_name}&time={time}',
            [
                'date' => '18.08.2026',
                'customer_name' => 'Max Mustermann',
                'time' => '10:30',
            ],
        );

        $this->assertSame(
            'https://example.com/thanks?date=18.08.2026&name=Max%20Mustermann&time=10%3A30',
            $url,
        );
    }
}
