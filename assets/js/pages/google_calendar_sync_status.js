/**
 * Google Calendar sync status page.
 */
App.Pages.GoogleCalendarSyncStatus = (function () {
    const $providerForm = $('#google-sync-provider-filters');
    const $providerBody = $('#google-sync-providers-table tbody');
    const $providerCount = $('#google-sync-providers-count');

    const $logForm = $('#google-sync-log-filters');
    const $logDate = $('#log-filter-date');
    const $logBody = $('#google-sync-logs-table tbody');
    const $logCount = $('#google-sync-logs-count');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function badge(enabled, onLabel, offLabel) {
        if (enabled) {
            return `<span class="badge text-bg-success">${escapeHtml(onLabel)}</span>`;
        }

        return `<span class="badge text-bg-secondary">${escapeHtml(offLabel)}</span>`;
    }

    function loadProviders(event) {
        if (event) {
            event.preventDefault();
        }

        App.Http.GoogleCalendarSyncStatus.providers({
            search: $('#provider-filter-search').val(),
            sync: $('#provider-filter-sync').val(),
            connected: $('#provider-filter-connected').val(),
        })
            .done((response) => {
                const providers = response.providers || [];
                $providerCount.text(response.count || providers.length);
                $providerBody.empty();

                if (!providers.length) {
                    $providerBody.append(
                        `<tr><td colspan="6" class="text-muted">${escapeHtml(lang('no_records_found') || '—')}</td></tr>`,
                    );
                    return;
                }

                providers.forEach((row) => {
                    $providerBody.append(`
                        <tr>
                            <td>${escapeHtml(row.name)} <small class="text-muted">#${escapeHtml(row.id)}</small></td>
                            <td>${escapeHtml(row.email || '—')}</td>
                            <td>${badge(row.sync_enabled, lang('active'), lang('inactive'))}</td>
                            <td>${badge(row.connected, lang('yes'), lang('no'))}</td>
                            <td><code>${escapeHtml(row.google_calendar || 'primary')}</code></td>
                            <td>-${escapeHtml(row.sync_past_days)} / +${escapeHtml(row.sync_future_days)} ${escapeHtml(lang('days') || 'days')}</td>
                        </tr>
                    `);
                });
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification(lang('unexpected_error') || 'Error');
            });
    }

    function fillLogDates(dates, selected) {
        const list = Array.isArray(dates) && dates.length ? dates : [selected || new Date().toISOString().slice(0, 10)];
        const current = selected || list[0];

        $logDate.empty();
        list.forEach((date) => {
            $logDate.append(new Option(date, date, date === current, date === current));
        });
    }

    function loadLogs(event) {
        if (event) {
            event.preventDefault();
        }

        App.Http.GoogleCalendarSyncStatus.logs({
            date: $logDate.val() || '',
            level: $('#log-filter-level').val(),
            search: $('#log-filter-search').val(),
            limit: 300,
        })
            .done((response) => {
                fillLogDates(response.available_dates || [], response.date);

                const lines = response.lines || [];
                $logCount.text(response.count || lines.length);
                $logBody.empty();

                if (!lines.length) {
                    $logBody.append(
                        `<tr><td colspan="3" class="text-muted">${escapeHtml(lang('no_records_found') || '—')}</td></tr>`,
                    );
                    return;
                }

                lines.forEach((line) => {
                    const level = String(line.level || 'info').toUpperCase();
                    const levelClass = level === 'ERROR' ? 'text-danger' : level === 'WARNING' ? 'text-warning' : '';
                    $logBody.append(`
                        <tr>
                            <td>${escapeHtml(line.timestamp || '—')}</td>
                            <td class="${levelClass}"><strong>${escapeHtml(level)}</strong></td>
                            <td><code class="small text-wrap d-inline-block" style="white-space: pre-wrap;">${escapeHtml(line.message)}</code></td>
                        </tr>
                    `);
                });
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification(lang('unexpected_error') || 'Error');
            });
    }

    $(function () {
        $providerForm.on('submit', loadProviders);
        $logForm.on('submit', loadLogs);

        // Initial date option so first request has a value.
        fillLogDates([], new Date().toISOString().slice(0, 10));

        loadProviders();
        loadLogs();
    });

    return {
        loadProviders,
        loadLogs,
    };
})();
