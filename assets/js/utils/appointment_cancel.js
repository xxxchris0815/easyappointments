/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Appointment soft-cancel helper (requires a status/reason selection).
 * ---------------------------------------------------------------------------- */

/**
 * Shared cancel dialog for calendar appointments.
 *
 * Always requires selecting a status/reason from appointment_status_options
 * before soft-cancelling (notify yes or no).
 */
window.App.Utils.AppointmentCancel = (function () {
    /**
     * Status options configured under Business Settings.
     *
     * @return {string[]}
     */
    function getStatusOptions() {
        const options = vars('appointment_status_options');

        if (!Array.isArray(options)) {
            return [];
        }

        return options
            .map((option) => String(option ?? '').trim())
            .filter((option) => option !== '');
    }

    /**
     * Build the status/reason <select> used in the cancel dialog.
     *
     * @return {jQuery}
     */
    function buildReasonSelect() {
        const $select = $('<select/>', {
            class: 'form-select w-100 mt-2',
            id: 'cancellation-reason',
        });

        $('<option/>', {
            value: '',
            text: lang('select_cancellation_status'),
        }).appendTo($select);

        getStatusOptions().forEach((option) => {
            $('<option/>', {
                value: option,
                text: option,
            }).appendTo($select);
        });

        return $select;
    }

    /**
     * Prompt for a required cancellation status/reason, then run the callback.
     *
     * @param {function(string): void} onConfirm Receives the selected status/reason.
     */
    function promptCancellationReason(onConfirm) {
        const $select = buildReasonSelect();

        if ($select.find('option').length <= 1) {
            App.Layouts.Backend.displayNotification(lang('cancellation_status_options_missing'));
            return;
        }

        App.Utils.Message.show(
            lang('cancel_appointment_title'),
            lang('select_appointment_cancellation_status'),
            [
                {
                    text: lang('cancel'),
                    click: (event, messageModal) => messageModal.hide(),
                },
                {
                    text: lang('cancel_appointment'),
                    click: (event, messageModal) => {
                        const reason = String($select.val() ?? '').trim();

                        if (!reason) {
                            $select.addClass('is-invalid');
                            App.Layouts.Backend.displayNotification(lang('cancellation_status_required'));
                            return;
                        }

                        messageModal.hide();
                        onConfirm(reason);
                    },
                },
            ],
            false,
        );

        $select.appendTo('#message-modal .modal-body');
        $select.on('change', () => $select.removeClass('is-invalid'));
    }

    /**
     * Full cancel flow: ask about notifications, then always require a status/reason.
     *
     * @param {number|string} appointmentId
     * @param {function(): void} [onDone]
     */
    function confirmAndCancel(appointmentId, onDone = null) {
        if (!appointmentId) {
            return;
        }

        App.Utils.Message.show(
            lang('cancel_appointment_title'),
            lang('notify_users_on_delete_question'),
            [
                {
                    text: lang('cancel'),
                    click: (event, notifyModal) => notifyModal.hide(),
                },
                {
                    text: lang('no'),
                    click: (event, notifyModal) => {
                        notifyModal.hide();
                        promptCancellationReason((reason) => {
                            App.Http.Calendar.deleteAppointment(appointmentId, reason, false).done(() => {
                                if (typeof onDone === 'function') {
                                    onDone();
                                }
                            });
                        });
                    },
                },
                {
                    text: lang('yes'),
                    click: (event, notifyModal) => {
                        notifyModal.hide();
                        promptCancellationReason((reason) => {
                            App.Http.Calendar.deleteAppointment(appointmentId, reason, true).done(() => {
                                if (typeof onDone === 'function') {
                                    onDone();
                                }
                            });
                        });
                    },
                },
            ],
        );
    }

    return {
        confirmAndCancel,
        promptCancellationReason,
        getStatusOptions,
    };
})();
