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
    const $lastResult = $('#google-sync-last-result');

    const canEdit = Boolean(vars('can_edit_system_settings'));

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

    function showResult(title, payload) {
        if (!$lastResult.length) {
            return;
        }

        $lastResult.removeClass('d-none');
        $lastResult.find('h6').text(title);
        $lastResult.find('pre').text(JSON.stringify(payload, null, 2));
    }

    function actionButtons(row) {
        const enabled = row.sync_enabled && row.connected;
        const count = Number(row.google_unavailability_count || 0);
        const diagnoseLabel = lang('google_diagnose_unavailabilities') || 'Diagnose';
        const resetLabel = lang('google_reset_unavailabilities') || 'Reset & re-sync';
        const resetTitle = lang('google_reset_unavailabilities_hint') || '';

        let html = `
            <button type="button"
                    class="btn btn-sm btn-outline-secondary google-diagnose-unavailabilities me-1"
                    data-provider-id="${escapeHtml(row.id)}"
                    data-provider-name="${escapeHtml(row.name)}"
                    data-sync-past-days="${escapeHtml(row.sync_past_days)}"
                    data-sync-future-days="${escapeHtml(row.sync_future_days)}"
                    title="${escapeHtml(lang('google_diagnose_unavailabilities_hint') || '')}">
                <i class="fas fa-stethoscope me-1"></i>${escapeHtml(diagnoseLabel)}
            </button>
        `;

        if (canEdit) {
            html += `
                <button type="button"
                        class="btn btn-sm btn-outline-danger google-reset-unavailabilities"
                        data-provider-id="${escapeHtml(row.id)}"
                        data-provider-name="${escapeHtml(row.name)}"
                        data-count="${escapeHtml(count)}"
                        ${enabled ? '' : 'disabled'}
                        title="${escapeHtml(resetTitle)}">
                    <i class="fas fa-sync-alt me-1"></i>${escapeHtml(resetLabel)}
                    <span class="badge text-bg-light text-dark ms-1">${escapeHtml(count)}</span>
                </button>
            `;
        }

        return html;
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
                        `<tr><td colspan="7" class="text-muted">${escapeHtml(lang('no_records_found') || '—')}</td></tr>`,
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
                            <td class="text-nowrap">${actionButtons(row)}</td>
                        </tr>
                    `);
                });
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification(lang('unexpected_error') || 'Error');
            });
    }

    function diagnoseUnavailabilities(event) {
        const $button = $(event.currentTarget);
        const providerId = Number($button.data('provider-id'));
        const providerName = String($button.data('provider-name') || providerId);

        if (!providerId) {
            return;
        }

        $button.prop('disabled', true);

        // Omit days= so the server uses the provider sync period (same horizon as Google sync).
        App.Http.GoogleCalendarSyncStatus.diagnose({
            provider_id: providerId,
        })
            .done((response) => {
                showResult(
                    (lang('google_diagnose_unavailabilities') || 'Diagnose') + ': ' + providerName,
                    response,
                );
                const past = response.sync_past_days ?? $button.data('sync-past-days') ?? '?';
                const future = response.sync_future_days ?? $button.data('sync-future-days') ?? '?';
                App.Layouts.Backend.displayNotification(
                    (lang('google_diagnose_ready') || 'Diagnosis ready — copy the JSON below.') +
                        ' window=-' +
                        past +
                        '/+' +
                        future +
                        'd slots=' +
                        (response.duplicate_slot_groups || []).length +
                        ' total=' +
                        (response.total_unavailabilities || 0),
                );
            })
            .fail((xhr) => {
                const message = xhr?.responseJSON?.message || lang('unexpected_error') || 'Error';
                App.Layouts.Backend.displayNotification(message);
            })
            .always(() => {
                $button.prop('disabled', false);
            });
    }

    function resetUnavailabilities(event) {
        const $button = $(event.currentTarget);
        const providerId = Number($button.data('provider-id'));
        const providerName = String($button.data('provider-name') || providerId);
        const count = Number($button.data('count') || 0);

        if (!providerId || $button.prop('disabled')) {
            return;
        }

        const confirmMessage = (lang('google_reset_unavailabilities_confirm') || '')
            .replace('{provider}', providerName)
            .replace('{count}', String(count));

        if (!window.confirm(confirmMessage)) {
            return;
        }

        $button.prop('disabled', true);

        App.Http.GoogleCalendarSyncStatus.resetUnavailabilities(providerId)
            .done((response) => {
                const deleted = response.deleted ?? 0;
                const googleDeleted = response.google_unavailable_deleted ?? 0;
                const exact = response.exact_duplicates_deleted ?? 0;
                const nested = response.nested_manual_deleted ?? 0;
                const message = (response.message || lang('google_unavailabilities_reset_success') || '')
                    .replace('{deleted}', String(deleted))
                    .replace('{google_deleted}', String(googleDeleted))
                    .replace('{exact}', String(exact))
                    .replace('{nested}', String(nested));

                App.Layouts.Backend.displayNotification(message);
                showResult((lang('google_reset_unavailabilities') || 'Reset') + ': ' + providerName, response);

                if (response.warning) {
                    App.Layouts.Backend.displayNotification(response.warning);
                }

                loadProviders();
                loadLogs();
            })
            .fail((xhr) => {
                const message =
                    xhr?.responseJSON?.message ||
                    lang('unexpected_error') ||
                    'Error';
                App.Layouts.Backend.displayNotification(message);
                showResult('Reset error: ' + providerName, xhr?.responseJSON || {message});
                $button.prop('disabled', false);
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
                        `<tr><td colspan="3" class="text-muted">${escapeHtml(
                            lang('google_sync_logs_empty_hint') || lang('no_records_found') || '—',
                        )}</td></tr>`,
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

    function copyLastResult() {
        const text = $lastResult.find('pre').text();

        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                App.Layouts.Backend.displayNotification(lang('copied_to_clipboard') || 'Copied');
            });
            return;
        }

        const $temp = $('<textarea>').val(text).appendTo('body').select();
        document.execCommand('copy');
        $temp.remove();
        App.Layouts.Backend.displayNotification(lang('copied_to_clipboard') || 'Copied');
    }

    $(function () {
        $providerForm.on('submit', loadProviders);
        $logForm.on('submit', loadLogs);
        $providerBody.on('click', '.google-reset-unavailabilities', resetUnavailabilities);
        $providerBody.on('click', '.google-diagnose-unavailabilities', diagnoseUnavailabilities);
        $('#google-sync-copy-result').on('click', copyLastResult);

        // Initial date option so first request has a value.
        fillLogDates([], new Date().toISOString().slice(0, 10));

        loadProviders();
        loadLogs();
    });

    return {
        loadProviders,
        loadLogs,
        resetUnavailabilities,
        diagnoseUnavailabilities,
    };
})();
