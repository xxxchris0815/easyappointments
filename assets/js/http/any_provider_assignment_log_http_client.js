/**
 * Any Provider assignment log HTTP client.
 */
App.Http.AnyProviderAssignmentLog = (function () {
    function explain(filters) {
        return $.get(App.Utils.Url.siteUrl('any_provider_assignment_log/explain'), filters || {});
    }

    function logs(filters) {
        return $.get(App.Utils.Url.siteUrl('any_provider_assignment_log/logs'), filters || {});
    }

    return {
        explain,
        logs,
    };
})();
