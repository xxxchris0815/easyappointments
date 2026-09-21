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

    /**
     * Delete Google-sourced unavailabilities for a provider and re-sync.
     *
     * @param {Number} providerId
     * @return {*|jQuery}
     */
    function resetUnavailabilities(providerId) {
        const url = App.Utils.Url.siteUrl('google_calendar_sync_status/reset_unavailabilities');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            provider_id: providerId,
        });
    }

    return {
        providers,
        logs,
        resetUnavailabilities,
    };
})();
