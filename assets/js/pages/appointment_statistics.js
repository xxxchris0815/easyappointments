/**
 * Appointment statistics page.
 */
App.Pages.AppointmentStatistics = (function () {
    const $form = $('#appointment-statistics-filters');
    const $tbody = $('#appointment-statistics-table tbody');
    const $count = $('#statistics-count');

    function search(event) {
        if (event) {
            event.preventDefault();
        }

        const data = {
            csrf_token: vars('csrf_token'),
            start_date: $('#filter-start-date').val(),
            end_date: $('#filter-end-date').val(),
            provider_id: $('#filter-provider').val(),
            service_id: $('#filter-service').val(),
            created_by: $('#filter-creator').val(),
            status: $('#filter-status').val(),
            utm_source: $('#filter-utm-source').val(),
            utm_medium: $('#filter-utm-medium').val(),
            utm_campaign: $('#filter-utm-campaign').val(),
            include_cancelled: $('#filter-include-cancelled').prop('checked') ? '1' : '0',
            sort: $('#filter-sort').val(),
            direction: $('#filter-direction').val(),
        };

        $.post(App.Utils.Url.siteUrl('appointment_statistics/search'), data)
            .done((response) => {
                const appointments = response.appointments || [];
                $count.text(response.count || appointments.length);
                $tbody.empty();

                appointments.forEach((row) => {
                    $tbody.append(`
                        <tr>
                            <td>${row.start_datetime || ''}</td>
                            <td>${row.end_datetime || ''}</td>
                            <td>${row.service_name || ''}</td>
                            <td>${row.provider_name || ''}</td>
                            <td>${row.customer_name || ''}<br><small class="text-muted">${row.customer_email || ''}</small></td>
                            <td>${row.creator_name || '—'}</td>
                            <td>${row.status || ''}</td>
                            <td>${row.utm_source || '—'}</td>
                            <td>${row.utm_campaign || '—'}</td>
                        </tr>
                    `);
                });
            })
            .fail(() => {
                App.Layouts.Backend.displayNotification(lang('unexpected_error') || 'Error');
            });
    }

    $(function () {
        $form.on('submit', search);
        search();
    });

    return {};
})();
