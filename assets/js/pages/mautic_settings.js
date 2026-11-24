/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 * ---------------------------------------------------------------------------- */

App.Pages.MauticSettings = (function () {
    const $saveSettings = $('#save-settings');

    function deserialize(mauticSettings) {
        mauticSettings.forEach((mauticSetting) => {
            const $field = $('[data-field="' + mauticSetting.name + '"]');

            $field.is(':checkbox')
                ? $field.prop('checked', Boolean(Number(mauticSetting.value)))
                : $field.val(mauticSetting.value);
        });
    }

    function serialize() {
        const mauticSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            mauticSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        return mauticSettings;
    }

    function onSaveSettingsClick() {
        const mauticSettings = serialize();

        App.Http.MauticSettings.save(mauticSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    function initialize() {
        $saveSettings.on('click', onSaveSettingsClick);
        deserialize(vars('mautic_settings') || []);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
