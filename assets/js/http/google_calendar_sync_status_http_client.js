/**
 * Google Calendar sync status HTTP client.
 */
App.Http.GoogleCalendarSyncStatus = (function () {
    function providers(filters) {
        return $.get(App.Utils.Url.siteUrl('google_calendar_sync_status/providers'), filters || {});
    }

    function logs(filters) {
        return $.get(App.Utils.Url.siteUrl('google_calendar_sync_status/logs'), filters || {});
    }

    return {
        providers,
        logs,
    };
})();
