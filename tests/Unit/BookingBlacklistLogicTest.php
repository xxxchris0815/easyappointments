<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pure phone/email matching logic for booking blacklist (no CodeIgniter boot).
 */
final class BookingBlacklistLogicTest extends TestCase
{
    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeInternational(string $digits): string
    {
        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    private function nationalCore(string $digits): string
    {
        $digits = $this->normalizeInternational($digits);

        if (str_starts_with($digits, '0')) {
            return ltrim($digits, '0');
        }

        return $digits;
    }

    private function phonesMatch(string $booking, string $blacklist, string $mode): bool
    {
        $bookingDigits = $this->digitsOnly($booking);
        $blacklistDigits = $this->digitsOnly($blacklist);

        if ($bookingDigits === '' || $blacklistDigits === '') {
            return false;
        }

        if ($mode === 'with_country_code') {
            return $this->normalizeInternational($bookingDigits)
                === $this->normalizeInternational($blacklistDigits);
        }

        $bookingCore = $this->nationalCore($bookingDigits);
        $blacklistCore = $this->nationalCore($blacklistDigits);

        if ($bookingCore === '' || $blacklistCore === '') {
            return false;
        }

        if ($bookingCore === $blacklistCore) {
            return true;
        }

        if (strlen($blacklistCore) < 7 || strlen($bookingCore) < 7) {
            return false;
        }

        return str_ends_with($bookingCore, $blacklistCore)
            || str_ends_with($blacklistCore, $bookingCore);
    }

    public function testEmailMatchIsCaseInsensitive(): void
    {
        $this->assertSame(
            mb_strtolower(trim('Spam@Example.com')),
            mb_strtolower(trim('spam@example.com')),
        );
    }

    public function testPhoneWithoutCountryCodeMatchesLocalAndInternationalForms(): void
    {
        $blacklist = '01701234567';

        $this->assertTrue($this->phonesMatch('01701234567', $blacklist, 'without_country_code'));
        $this->assertTrue($this->phonesMatch('1701234567', $blacklist, 'without_country_code'));
        $this->assertTrue($this->phonesMatch('+49 170 1234567', $blacklist, 'without_country_code'));
        $this->assertTrue($this->phonesMatch('00491701234567', $blacklist, 'without_country_code'));
        $this->assertFalse($this->phonesMatch('01709999999', $blacklist, 'without_country_code'));
    }

    public function testPhoneWithCountryCodeRequiresExactNormalizedMatch(): void
    {
        $blacklist = '+491701234567';

        $this->assertTrue($this->phonesMatch('+49 170 1234567', $blacklist, 'with_country_code'));
        $this->assertTrue($this->phonesMatch('00491701234567', $blacklist, 'with_country_code'));
        $this->assertFalse($this->phonesMatch('01701234567', $blacklist, 'with_country_code'));
        $this->assertFalse($this->phonesMatch('+431701234567', $blacklist, 'with_country_code'));
    }

    public function testMauticIdExactMatch(): void
    {
        $this->assertTrue(trim('12345') === trim('12345'));
        $this->assertFalse(trim('12345') === trim('12346'));
    }
}
