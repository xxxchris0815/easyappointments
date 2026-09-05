/**
 * SMTP settings page.
 */
App.Pages.SmtpSettings = (function () {
    const $saveSettings = $('#save-settings');
    const $testSettings = $('#test-smtp-settings');
    const $testRecipient = $('#smtp-test-recipient');

    function serialize() {
        const settings = [];

        $('#smtp-settings [data-field]').each((index, element) => {
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
            const $field = $('#smtp-settings [data-field="' + setting.name + '"]');

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

    function onTestClick() {
        const smtpSettings = serialize();
        // Ensure enabled for the test attempt.
        const enabled = smtpSettings.find((item) => item.name === 'smtp_enabled');
        if (enabled) {
            enabled.value = '1';
        } else {
            smtpSettings.push({name: 'smtp_enabled', value: '1'});
        }
        $('#smtp-enabled').prop('checked', true);

        App.Http.SmtpSettings.test(smtpSettings, $testRecipient.val())
            .done((response) => {
                App.Layouts.Backend.displayNotification(response.message || lang('smtp_test_email_sent_generic'));
            })
            .fail((xhr) => {
                const message =
                    xhr?.responseJSON?.message ||
                    xhr?.responseText ||
                    lang('smtp_test_email_failed');
                App.Layouts.Backend.displayNotification(message);
            });
    }

    $(function () {
        deserialize(vars('smtp_settings') || []);
        $saveSettings.on('click', onSaveClick);
        $testSettings.on('click', onTestClick);
    });

    return {};
})();
