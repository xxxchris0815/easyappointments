<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Custom fields helper.
 * ---------------------------------------------------------------------------- */

if (!function_exists('max_custom_fields')) {
    /**
     * Maximum supported custom fields.
     */
    function max_custom_fields(): int
    {
        return defined('MAX_CUSTOM_FIELDS') ? (int) MAX_CUSTOM_FIELDS : 20;
    }
}

if (!function_exists('custom_fields_count')) {
    /**
     * Active custom fields count configured by the administrator.
     */
    function custom_fields_count(): int
    {
        $count = (int) setting('custom_fields_count', 5);

        if ($count < 5) {
            $count = 5;
        }

        if ($count > max_custom_fields()) {
            $count = max_custom_fields();
        }

        return $count;
    }
}

if (!function_exists('custom_field_keys')) {
    /**
     * List of active custom field database keys.
     *
     * @return string[]
     */
    function custom_field_keys(): array
    {
        $keys = [];

        for ($i = 1; $i <= custom_fields_count(); $i++) {
            $keys[] = 'custom_field_' . $i;
        }

        return $keys;
    }
}
