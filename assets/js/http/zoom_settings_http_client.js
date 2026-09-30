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
 * Zoom Settings HTTP client.
 *
 * This module implements the Zoom settings related HTTP requests.
 */
App.Http.ZoomSettings = (function () {
    /**
     * Save Zoom settings.
     *
     * @param {Array} zoomSettings
     *
     * @return {Object}
     */
    function save(zoomSettings) {
        const url = App.Utils.Url.siteUrl('zoom_settings/save');

        const data = {
            csrf_token: vars('csrf_token'),
            zoom_settings: zoomSettings,
        };

        return $.post(url, data);
    }

    return {
        save,
    };
})();
