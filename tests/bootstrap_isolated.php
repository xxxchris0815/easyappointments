<?php

/**
 * Lightweight bootstrap for isolated unit tests that stub setting()/get_instance().
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

if (!defined('MAX_CUSTOM_FIELDS')) {
    define('MAX_CUSTOM_FIELDS', 20);
}

if (!class_exists('CI_Controller', false)) {
    class CI_Controller
    {
    }
}

if (!class_exists('EA_Controller', false)) {
    class EA_Controller extends CI_Controller
    {
    }
}

/**
 * Shared settings bag used by isolated unit tests.
 *
 * @var array<string, mixed>
 */
$GLOBALS['__ea_test_settings'] = [];

if (!function_exists('setting')) {
    function setting(string $name, mixed $default = null): mixed
    {
        return $GLOBALS['__ea_test_settings'][$name] ?? $default;
    }
}

if (!function_exists('get_instance')) {
    function &get_instance()
    {
        static $ci;

        if ($ci === null) {
            $ci = new EA_Controller();
        }

        return $ci;
    }
}
