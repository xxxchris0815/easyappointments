/**
 * Any Provider assignment calculation / log page.
 */
App.Pages.AnyProviderAssignmentLog = (function () {
    const $form = $('#any-provider-explain-form');
    const $providerBody = $('#any-provider-providers-table tbody');
    const $providerCount = $('#any-provider-providers-count');
    const $summary = $('#any-provider-selection-summary');
    const $lastResult = $('#any-provider-last-result');
    const $logBody = $('#any-provider-logs-table tbody');
    const $logCount = $('#any-provider-logs-count');
    const $refreshLogs = $('#any-provider-refresh-logs');
    const $copyResult = $('#any-provider-copy-result');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function rejectionLabel(reason) {
        const map = {
            does_not_offer_service: lang('any_provider_reason_does_not_offer_service'),
            no_available_hours: lang('any_provider_reason_no_available_hours'),
            slot_unavailable: lang('any_provider_reason_slot_unavailable'),
            preferred_provider_still_available: lang('any_provider_reason_preferred_kept'),
            preferred_provider_not_in_candidates: lang('any_provider_reason_preferred_unavailable'),
            preferred_provider_not_available_and_no_candidates: lang('any_provider_reason_no_candidates'),
            no_candidates: lang('any_provider_reason_no_candidates'),
        };

        return map[reason] || reason || '—';
    }

    function yesNo(value) {
        return value
            ? `<span class="badge text-bg-success">${escapeHtml(lang('yes'))}</span>`
            : `<span class="badge text-bg-secondary">${escapeHtml(lang('no'))}</span>`;
    }

    function showResult(report) {
        $lastResult.removeClass('d-none');
        $lastResult.find('pre').text(JSON.stringify(report, null, 2));
    }

    function renderSummary(report) {
        const selection = report.selection || {};
        const preferred = report.preferred || {};
        const settings = report.settings || {};
        const selectedName = selection.selected_provider_name || ('#' + (selection.selected_provider_id || '—'));
        const method = selection.method || '—';
        const basisMessage = (selection.basis && selection.basis.message) || '';

        let html = `<strong>${escapeHtml(lang('provider'))}:</strong> ${escapeHtml(selectedName)}`;
        html += ` &nbsp;|&nbsp; <strong>${escapeHtml(lang('any_provider_selection_mode'))}:</strong> ${escapeHtml(method)}`;
        html += ` &nbsp;|&nbsp; <strong>${escapeHtml(lang('any_provider_candidates'))}:</strong> ${escapeHtml(report.candidate_count || 0)}`;
        html += ` &nbsp;|&nbsp; <strong>${escapeHtml(lang('any_provider_rr_counter'))}:</strong> ${escapeHtml(settings.rr_counter)} → ${escapeHtml(settings.rr_counter_after)}`;

        if (preferred.provider_id) {
            html += `<br><span class="small">${escapeHtml(lang('any_provider_preferred_provider'))}: #${escapeHtml(preferred.provider_id)} — ${escapeHtml(rejectionLabel(preferred.reason))}</span>`;
        }

        if (basisMessage) {
            html += `<br><span class="small text-muted">${escapeHtml(basisMessage)}</span>`;
        }

        $summary.removeClass('d-none alert-secondary alert-success alert-warning')
            .addClass(selection.selected_provider_id ? 'alert-success' : 'alert-warning')
            .html(html);
    }

    function renderProviders(providers) {
        $providerBody.empty();

        (providers || []).forEach((row) => {
            const hours = (row.available_hours || []).join(', ') || '—';
            const hoursLabel = row.available_hours_count
                ? `${escapeHtml(row.available_hours_count)} (${escapeHtml(hours)})`
                : '—';
            const selectedClass = row.is_candidate ? '' : 'table-secondary';

            $providerBody.append(`
                <tr class="${selectedClass}" data-provider-id="${escapeHtml(row.id)}">
                    <td>
                        <div class="fw-semibold">${escapeHtml(row.name)}</div>
                        <div class="small text-muted">#${escapeHtml(row.id)} ${escapeHtml(row.email || '')}</div>
                    </td>
                    <td>${yesNo(row.offers_service)}</td>
                    <td class="small">${hoursLabel}</td>
                    <td>${escapeHtml(row.weight)}</td>
                    <td>${yesNo(row.is_candidate)}</td>
                    <td class="small">${escapeHtml(rejectionLabel(row.rejection_reason))}</td>
                </tr>
            `);
        });

        $providerCount.text((providers || []).length);
    }

    function highlightSelected(report) {
        const selectedId = report?.selection?.selected_provider_id;

        if (!selectedId) {
            return;
        }

        $providerBody.find('tr').each(function () {
            const $row = $(this);

            if (String($row.data('provider-id')) === String(selectedId)) {
                $row.removeClass('table-secondary').addClass('table-success');
            }
        });
    }

    function runExplain(event) {
        if (event) {
            event.preventDefault();
        }

        const serviceId = $('#explain-service-id').val();

        if (!serviceId) {
            return;
        }

        const filters = {
            service_id: serviceId,
            date: $('#explain-date').val(),
            hour: $('#explain-hour').val() || '',
            preferred_provider_id: $('#explain-preferred-provider-id').val() || '',
            exclude_appointment_id: $('#explain-exclude-appointment-id').val() || '',
        };

        App.Http.AnyProviderAssignmentLog.explain(filters)
            .done((response) => {
                const report = response.report || {};
                renderProviders(report.providers || []);
                renderSummary(report);
                highlightSelected(report);
                showResult(report);

                if (report.settings) {
                    $('#any-provider-current-mode').text(report.settings.mode || '');
                    $('#any-provider-current-rr').text(report.settings.rr_counter ?? '');
                }
            })
            .fail((xhr) => {
                const message = xhr?.responseJSON?.message || xhr.statusText || 'Request failed';
                $summary.removeClass('d-none alert-success alert-secondary')
                    .addClass('alert-warning')
                    .text(message);
            });
    }

    function renderLogs(entries) {
        $logBody.empty();

        const rows = (entries || []).slice().reverse();

        rows.forEach((entry) => {
            const input = entry.input || {};
            const settings = entry.settings || {};
            const selection = entry.selection || {};
            const preferred = entry.preferred || {};
            const reason = selection.method === 'preferred_provider'
                ? preferred.reason
                : (selection.basis && selection.basis.message) || selection.method;

            $logBody.append(`
                <tr>
                    <td class="small">${escapeHtml(entry.timestamp || '')}<br>${escapeHtml(input.date || '')}</td>
                    <td class="small">${escapeHtml(input.service_name || ('#' + (input.service_id || '')))}</td>
                    <td>${escapeHtml(input.hour || '—')}</td>
                    <td class="small">${escapeHtml(settings.mode || selection.method || '—')}</td>
                    <td>${escapeHtml(entry.candidate_count ?? (entry.candidates || []).length)}</td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(selection.selected_provider_name || '—')}</div>
                        <div class="small text-muted">#${escapeHtml(selection.selected_provider_id || '—')}</div>
                    </td>
                    <td class="small">${escapeHtml(rejectionLabel(reason))}</td>
                </tr>
            `);
        });

        $logCount.text(rows.length);
    }

    function loadLogs() {
        App.Http.AnyProviderAssignmentLog.logs({limit: 100})
            .done((response) => {
                renderLogs(response.entries || []);

                if (response.settings) {
                    $('#any-provider-current-mode').text(response.settings.mode || '');
                    $('#any-provider-current-rr').text(response.settings.rr_counter ?? '');
                }
            });
    }

    function copyResult() {
        const text = $lastResult.find('pre').text();

        if (!text || !navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(text);
    }

    function initialize() {
        $form.on('submit', runExplain);
        $refreshLogs.on('click', loadLogs);
        $copyResult.on('click', copyResult);
        loadLogs();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
        runExplain,
        loadLogs,
    };
})();
