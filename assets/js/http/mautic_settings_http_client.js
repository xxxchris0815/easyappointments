/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Http.MauticSettings = (function () {
    function save(mauticSettings) {
        const url = App.Utils.Url.siteUrl('mautic_settings/save');

        const data = {
            csrf_token: vars('csrf_token'),
            mautic_settings: mauticSettings,
        };

        return $.post(url, data);
    }

    return {
        save,
    };
})();
