/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Booking settings page.
 *
 * This module implements the functionality of the booking settings page.
 */
App.Pages.BookingSettings = (function () {
    /**
     * Check if the form has invalid values.
     *
     * @return {Boolean}
     */
    function isInvalid() {
        try {
            $('#booking-settings .is-invalid').removeClass('is-invalid');

            // Validate required fields.

            let missingRequiredFields = false;

            $('#booking-settings .required').each((index, requiredField) => {
                const $requiredField = $(requiredField);

                if (!$requiredField.val()) {
                    $requiredField.addClass('is-invalid');
                    missingRequiredFields = true;
                }
            });

            if (missingRequiredFields) {
                throw new Error(lang('fields_are_required'));
            }

            // Ensure there is at least one field displayed.

            if (!$('.display-switch:checked').length) {
                throw new Error(lang('at_least_one_field'));
            }

            // Ensure there is at least one field required.

            if (!$('.require-switch:checked').length) {
                throw new Error(lang('at_least_one_field_required'));
            }

            return false;
        } catch (error) {
            App.Layouts.Backend.displayNotification(error.message);
            return true;
        }
    }

    function parseReminderRules(rawValue) {
        try {
            const parsed = typeof rawValue === 'string' ? JSON.parse(rawValue || '[]') : rawValue;
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function syncReminderField() {
        const rules = [];

        $('#appointment-reminder-rules .appointment-reminder-rule').each((index, row) => {
            const $row = $(row);
            const channels = [];

            if ($row.find('.reminder-channel-email').prop('checked')) {
                channels.push('email');
            }

            if ($row.find('.reminder-channel-webhook').prop('checked')) {
                channels.push('webhook');
            }

            rules.push({
                id: $row.data('id') || 'r' + (index + 1),
                offset: Number($row.find('.reminder-offset').val() || 0),
                unit: $row.find('.reminder-unit').val() || 'hours',
                channels,
            });
        });

        $('#appointment-reminders').val(JSON.stringify(rules));
    }

    function renderReminderRules(rules) {
        const $reminderRules = $('#appointment-reminder-rules');

        $reminderRules.empty();

        (rules || []).forEach((rule, index) => {
            const id = rule.id || 'r' + (index + 1);
            const channels = rule.channels || [];

            const $row = $(`
                <div class="appointment-reminder-rule border rounded p-2 mb-2" data-id="${id}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">${lang('reminder_offset')}</label>
                            <input type="number" min="0" class="form-control reminder-offset" value="${Number(rule.offset || 0)}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">${lang('reminder_unit')}</label>
                            <select class="form-select reminder-unit">
                                <option value="minutes">${lang('minutes')}</option>
                                <option value="hours">${lang('hours')}</option>
                                <option value="days">${lang('days')}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">${lang('reminder_channels')}</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input reminder-channel-email" type="checkbox" ${channels.includes('email') ? 'checked' : ''}>
                                <label class="form-check-label">${lang('email')}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input reminder-channel-webhook" type="checkbox" ${channels.includes('webhook') ? 'checked' : ''}>
                                <label class="form-check-label">${lang('webhook')}</label>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-reminder">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);

            $row.find('.reminder-unit').val(rule.unit || 'hours');
            $reminderRules.append($row);
        });

        syncReminderField();
    }

    function onAddReminderClick() {
        const rules = parseReminderRules($('#appointment-reminders').val());

        rules.push({
            id: 'r' + Date.now(),
            offset: 24,
            unit: 'hours',
            channels: ['email'],
        });

        renderReminderRules(rules);
    }

    /**
     * Apply the booking settings into the page.
     *
     * @param {Object} bookingSettings
     */
    function deserialize(bookingSettings) {
        const $disableBookingMessage = $('#disable-booking-message');

        (bookingSettings || []).forEach((bookingSetting) => {
            if (bookingSetting.name === 'disable_booking_message') {
                $disableBookingMessage.trumbowyg('html', bookingSetting.value);
                return;
            }

            if (bookingSetting.name === 'appointment_reminders') {
                renderReminderRules(parseReminderRules(bookingSetting.value));
                return;
            }

            const $field = $('[data-field="' + bookingSetting.name + '"]');

            if ($field.is(':checkbox')) {
                $field.prop('checked', Boolean(Number(bookingSetting.value)));
            } else {
                $field.val(bookingSetting.value);
            }
        });
    }

    /**
     * Serialize the page values into an array.
     *
     * @returns {Array}
     */
    function serialize() {
        syncReminderField();

        const bookingSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            bookingSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        bookingSettings.push({
            name: 'disable_booking_message',
            value: $('#disable-booking-message').trumbowyg('html'),
        });

        return bookingSettings;
    }

    /**
     * Update the UI based on the display switch state.
     *
     * @param {jQuery} $displaySwitch
     */
    function updateDisplaySwitch($displaySwitch) {
        const isChecked = $displaySwitch.prop('checked');

        const $formGroup = $displaySwitch.closest('.form-group');

        $formGroup.find('.require-switch').prop('disabled', !isChecked);

        $formGroup.find('.form-label, .form-control').toggleClass('opacity-25', !isChecked);

        if (!isChecked) {
            $formGroup.find('.require-switch').prop('checked', false);
            $formGroup.find('.text-danger').hide();
        }
    }

    /**
     * Update the UI based on the require switch state.
     *
     * @param {jQuery} $requireSwitch
     */
    function updateRequireSwitch($requireSwitch) {
        const isChecked = $requireSwitch.prop('checked');

        const $formGroup = $requireSwitch.closest('.form-group');

        $formGroup.find('.text-danger').toggle(isChecked);
    }

    /**
     * Update the UI based on the initial values.
     */
    function applyInitialState() {
        const $bookingSettings = $('#booking-settings');
        const $disableBooking = $('#disable-booking');
        const $disableBookingMessage = $('#disable-booking-message');

        $bookingSettings.find('.display-switch').each((index, displaySwitchEl) => {
            const $displaySwitch = $(displaySwitchEl);

            updateDisplaySwitch($displaySwitch);
        });

        $bookingSettings.find('.require-switch').each((index, requireSwitchEl) => {
            const $requireSwitch = $(requireSwitchEl);

            updateRequireSwitch($requireSwitch);
        });

        $disableBookingMessage.closest('.form-group').prop('hidden', !$disableBooking.prop('checked'));
    }

    /**
     * Save the account information.
     */
    function onSaveSettingsClick() {
        if (isInvalid()) {
            return;
        }

        const bookingSettings = serialize();

        App.Http.BookingSettings.save(bookingSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    /**
     * Update the UI.
     *
     * @param {jQuery} event
     */
    function onDisplaySwitchClick(event) {
        const $displaySwitch = $(event.target);

        updateDisplaySwitch($displaySwitch);
    }

    /**
     * Update the UI.
     *
     * @param {Event} event
     */
    function onRequireSwitchClick(event) {
        const $requireSwitch = $(event.target);

        updateRequireSwitch($requireSwitch);
    }

    /**
     * Toggle the message container.
     */
    function onDisableBookingClick() {
        const $disableBooking = $('#disable-booking');
        const $disableBookingMessage = $('#disable-booking-message');

        $disableBookingMessage.closest('.form-group').prop('hidden', !$disableBooking.prop('checked'));
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        const $bookingSettings = $('#booking-settings');
        const $saveSettings = $('#save-settings');
        const $disableBooking = $('#disable-booking');
        const $disableBookingMessage = $('#disable-booking-message');
        const $reminderRules = $('#appointment-reminder-rules');
        const bookingSettings = vars('booking_settings');

        $saveSettings.on('click', onSaveSettingsClick);

        $disableBooking.on('click', onDisableBookingClick);

        $bookingSettings
            .on('click', '.display-switch', onDisplaySwitchClick)
            .on('click', '.require-switch', onRequireSwitchClick)
            .on('click', '#add-appointment-reminder', onAddReminderClick);

        $reminderRules.on('click', '.remove-reminder', (event) => {
            $(event.currentTarget).closest('.appointment-reminder-rule').remove();
            syncReminderField();
        });

        $reminderRules.on('change input', 'input, select', syncReminderField);

        $disableBookingMessage.trumbowyg();

        deserialize(bookingSettings);

        applyInitialState();
    }

    $(initialize);

    return {
        parseReminderRules,
        renderReminderRules,
        syncReminderField,
        onAddReminderClick,
    };
})();
