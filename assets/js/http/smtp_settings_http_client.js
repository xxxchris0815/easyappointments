/**
 * SMTP Settings HTTP client.
 */
App.Http.SmtpSettings = (function () {
    function save(smtpSettings) {
        const url = App.Utils.Url.siteUrl('smtp_settings/save');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            smtp_settings: smtpSettings,
        });
    }

    function test(smtpSettings, recipientEmail) {
        const url = App.Utils.Url.siteUrl('smtp_settings/test');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            smtp_settings: smtpSettings,
            recipient_email: recipientEmail || '',
        });
    }

    return {
        save,
        test,
    };
})();
