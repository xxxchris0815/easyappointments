/**
 * Booking blacklist settings HTTP client.
 */
App.Http.BookingBlacklistSettings = (function () {
    function save(entries) {
        return $.post(App.Utils.Url.siteUrl('booking_blacklist_settings/save'), {
            csrf_token: vars('csrf_token'),
            booking_blacklist: JSON.stringify(entries || []),
        });
    }

    function logs(filters) {
        return $.get(App.Utils.Url.siteUrl('booking_blacklist_settings/logs'), filters || {});
    }

    return {
        save,
        logs,
    };
})();
