<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for custom_fields_helper.php using a stubbed setting() function.
 */
class CustomFieldsHelperTest extends TestCase
{
    private static bool $helperLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$helperLoaded) {
            require_once dirname(__DIR__, 2) . '/application/helpers/custom_fields_helper.php';
            self::$helperLoaded = true;
        }
    }

    protected function setUp(): void
    {
        $GLOBALS['__ea_test_settings'] = [];
    }

    public function testMaxCustomFieldsReturnsConstant(): void
    {
        $this->assertSame(20, max_custom_fields());
    }

    public function testCustomFieldsCountDefaultsToFive(): void
    {
        $this->assertSame(5, custom_fields_count());
    }

    public function testCustomFieldsCountRespectsConfiguredValue(): void
    {
        $GLOBALS['__ea_test_settings']['custom_fields_count'] = '8';

        $this->assertSame(8, custom_fields_count());
    }

    public function testCustomFieldsCountClampsBelowMinimum(): void
    {
        $GLOBALS['__ea_test_settings']['custom_fields_count'] = '2';

        $this->assertSame(5, custom_fields_count());
    }

    public function testCustomFieldsCountClampsAboveMaximum(): void
    {
        $GLOBALS['__ea_test_settings']['custom_fields_count'] = '99';

        $this->assertSame(20, custom_fields_count());
    }

    public function testCustomFieldKeysReturnsExpectedKeys(): void
    {
        $GLOBALS['__ea_test_settings']['custom_fields_count'] = '7';

        $this->assertSame(
            [
                'custom_field_1',
                'custom_field_2',
                'custom_field_3',
                'custom_field_4',
                'custom_field_5',
                'custom_field_6',
                'custom_field_7',
            ],
            custom_field_keys(),
        );
    }
}
