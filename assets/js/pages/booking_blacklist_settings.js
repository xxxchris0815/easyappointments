/**
 * Booking blacklist admin settings page.
 */
App.Pages.BookingBlacklistSettings = (function () {
    const $entries = $('#booking-blacklist-entries');
    const $add = $('#add-booking-blacklist-entry');
    const $save = $('#save-booking-blacklist');
    const $logBody = $('#booking-blacklist-logs-table tbody');
    const $logCount = $('#booking-blacklist-logs-count');
    const $refreshLogs = $('#refresh-booking-blacklist-logs');
    const canEdit = Boolean(vars('can_edit_system_settings'));

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function typeOptions(selected) {
        const types = [
            {value: 'email', label: lang('email')},
            {value: 'phone', label: lang('phone_number')},
            {value: 'mautic_id', label: 'Mautic ID'},
        ];

        return types
            .map((type) => {
                const sel = type.value === selected ? 'selected' : '';
                return `<option value="${escapeHtml(type.value)}" ${sel}>${escapeHtml(type.label)}</option>`;
            })
            .join('');
    }

    function phoneModeOptions(selected) {
        const modes = [
            {
                value: 'without_country_code',
                label: lang('booking_blacklist_phone_without_country'),
            },
            {
                value: 'with_country_code',
                label: lang('booking_blacklist_phone_with_country'),
            },
        ];

        return modes
            .map((mode) => {
                const sel = mode.value === selected ? 'selected' : '';
                return `<option value="${escapeHtml(mode.value)}" ${sel}>${escapeHtml(mode.label)}</option>`;
            })
            .join('');
    }

    function renderEntry(entry = {}) {
        const type = entry.type || 'email';
        const phoneMode = entry.phone_match_mode || 'without_country_code';
        const disabled = canEdit ? '' : 'disabled';

        return $(`
            <div class="border rounded p-3 mb-3 booking-blacklist-entry">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">${escapeHtml(lang('booking_blacklist_entry_type'))}</label>
                        <select class="form-select blacklist-type" ${disabled}>
                            ${typeOptions(type)}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">${escapeHtml(lang('booking_blacklist_entry_value'))}</label>
                        <input type="text" class="form-control blacklist-value" ${disabled}
                               value="${escapeHtml(entry.value || '')}"
                               placeholder="email / phone / id">
                    </div>
                    <div class="col-md-3 blacklist-phone-mode-wrap" ${type === 'phone' ? '' : 'hidden'}>
                        <label class="form-label">${escapeHtml(lang('booking_blacklist_phone_match_mode'))}</label>
                        <select class="form-select blacklist-phone-mode" ${disabled}>
                            ${phoneModeOptions(phoneMode)}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">${escapeHtml(lang('notes'))}</label>
                        <input type="text" class="form-control blacklist-note" ${disabled}
                               value="${escapeHtml(entry.note || '')}">
                    </div>
                    <div class="col-md-1">
                        ${
                            canEdit
                                ? `<button type="button" class="btn btn-outline-danger w-100 delete-blacklist-entry" title="${escapeHtml(lang('delete'))}">
                                       <i class="fas fa-trash"></i>
                                   </button>`
                                : ''
                        }
                    </div>
                </div>
            </div>
        `);
    }

    function collectEntries() {
        const entries = [];

        $entries.find('.booking-blacklist-entry').each((index, el) => {
            const $row = $(el);
            const type = $row.find('.blacklist-type').val();
            const value = String($row.find('.blacklist-value').val() || '').trim();

            if (!value) {
                return;
            }

            const entry = {
                type,
                value,
                note: String($row.find('.blacklist-note').val() || '').trim(),
            };

            if (type === 'phone') {
                entry.phone_match_mode = $row.find('.blacklist-phone-mode').val() || 'without_country_code';
            }

            entries.push(entry);
        });

        return entries;
    }

    function onTypeChange(event) {
        const $row = $(event.currentTarget).closest('.booking-blacklist-entry');
        const type = $(event.currentTarget).val();
        $row.find('.blacklist-phone-mode-wrap').prop('hidden', type !== 'phone');
    }

    function onAddClick() {
        $entries.append(renderEntry({type: 'email'}));
    }

    function onDeleteClick(event) {
        $(event.currentTarget).closest('.booking-blacklist-entry').remove();
    }

    function onSaveClick() {
        App.Http.BookingBlacklistSettings.save(collectEntries()).done((response) => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
            $entries.empty();
            (response.booking_blacklist || []).forEach((entry) => {
                $entries.append(renderEntry(entry));
            });
        });
    }

    function renderLogs(entries) {
        $logBody.empty();
        const rows = (entries || []).slice().reverse();

        rows.forEach((entry) => {
            const matched = entry.matched || {};
            const customer = entry.customer || {};
            const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ');

            $logBody.append(`
                <tr>
                    <td class="small">${escapeHtml(entry.timestamp || '')}</td>
                    <td>${escapeHtml(matched.type || '')}</td>
                    <td class="small">${escapeHtml(matched.value || '')}</td>
                    <td>${escapeHtml(name || '—')}</td>
                    <td class="small">${escapeHtml(customer.email || '')}</td>
                    <td class="small">${escapeHtml(customer.phone_number || '')}</td>
                    <td class="small">${escapeHtml(entry.mautic_id || '')}</td>
                </tr>
            `);
        });

        $logCount.text(rows.length);
    }

    function loadLogs() {
        App.Http.BookingBlacklistSettings.logs({limit: 50}).done((response) => {
            renderLogs(response.entries || []);
        });
    }

    function initialize() {
        const initial = vars('booking_blacklist') || [];

        if (!initial.length && canEdit) {
            // Empty list is fine.
        }

        initial.forEach((entry) => {
            $entries.append(renderEntry(entry));
        });

        $entries.on('change', '.blacklist-type', onTypeChange);
        $entries.on('click', '.delete-blacklist-entry', onDeleteClick);
        $add.on('click', onAddClick);
        $save.on('click', onSaveClick);
        $refreshLogs.on('click', loadLogs);
        loadLogs();
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        initialize,
        collectEntries,
        loadLogs,
    };
})();
