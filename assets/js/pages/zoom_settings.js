/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.6.0
 * ---------------------------------------------------------------------------- */

/**
 * Zoom settings page.
 *
 * This module implements the functionality of the Zoom settings page.
 */
App.Pages.ZoomSettings = (function () {
    const $saveSettings = $('#save-settings');

    /**
     * Check if the form has invalid values.
     *
     * @return {Boolean}
     */
    function isInvalid() {
        try {
            $('#zoom-settings .is-invalid').removeClass('is-invalid');

            return false;
        } catch (error) {
            App.Layouts.Backend.displayNotification(error.message);
            return true;
        }
    }

    /**
     * Deserialize the Zoom settings.
     *
     * @param {Array} zoomSettings
     */
    function deserialize(zoomSettings) {
        zoomSettings.forEach((zoomSetting) => {
            const $field = $('[data-field="' + zoomSetting.name + '"]');

            $field.is(':checkbox')
                ? $field.prop('checked', Boolean(Number(zoomSetting.value)))
                : $field.val(zoomSetting.value);
        });
    }

    /**
     * Serialize the Zoom settings.
     *
     * @return {Array}
     */
    function serialize() {
        const zoomSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            zoomSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        return zoomSettings;
    }

    /**
     * Save the Zoom settings.
     */
    function onSaveSettingsClick() {
        if (isInvalid()) {
            App.Layouts.Backend.displayNotification(lang('settings_are_invalid'));
            return;
        }

        const zoomSettings = serialize();

        App.Http.ZoomSettings.save(zoomSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        $saveSettings.on('click', onSaveSettingsClick);

        const zoomSettings = vars('zoom_settings');

        deserialize(zoomSettings);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
