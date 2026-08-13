/**
 * SMTP settings page.
 */
App.Pages.SmtpSettings = (function () {
    const $saveSettings = $('#save-settings');

    function serialize() {
        const settings = [];

        $('[data-field]').each((index, element) => {
            const $el = $(element);
            const name = $el.data('field');
            let value;

            if ($el.is(':checkbox')) {
                value = $el.prop('checked') ? '1' : '0';
            } else {
                value = $el.val();
            }

            settings.push({name, value});
        });

        return settings;
    }

    function deserialize(settings) {
        settings.forEach((setting) => {
            const $field = $('[data-field="' + setting.name + '"]');

            if (!$field.length) {
                return;
            }

            if ($field.is(':checkbox')) {
                $field.prop('checked', Number(setting.value) === 1);
            } else {
                $field.val(setting.value);
            }
        });
    }

    function onSaveClick() {
        const smtpSettings = serialize();

        App.Http.SmtpSettings.save(smtpSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    $(function () {
        deserialize(vars('smtp_settings') || []);
        $saveSettings.on('click', onSaveClick);
    });

    return {};
})();
