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
 * Booking HTTP client.
 *
 * This module implements the booking related HTTP requests.
 *
 * Old Name: FrontendBookApi
 */
App.Http.Booking = (function () {
    const $selectDate = $('#select-date');
    const $selectService = $('#select-service');
    const $selectProvider = $('#select-provider');
    const $availableHours = $('#available-hours');
    const $availableHoursMore = $('#available-hours-more');
    const $loadMoreHours = $('#load-more-hours');
    const $captchaHint = $('#captcha-hint');
    const $captchaTitle = $('.captcha-title');

    const MONTH_SEARCH_LIMIT = 2; // Months in the future

    const moment = window.moment;

    let unavailableDatesBackup;
    let selectedDateStringBackup;
    let processingUnavailableDates = false;
    let searchedMonthStart;
    let searchedMonthCounter = 0;
    let pendingHourButtons = [];
    let renderedHourCount = 0;

    function timeslotColumns() {
        return Math.max(1, Math.min(4, Number(vars('booking_timeslot_columns') || 1)));
    }

    function timeslotPageSize() {
        return Math.max(0, Number(vars('booking_timeslot_page_size') || 0));
    }

    function applyTimeslotLayoutClasses() {
        $availableHours
            .removeClass('timeslot-cols-1 timeslot-cols-2 timeslot-cols-3 timeslot-cols-4')
            .addClass('timeslot-cols-' + timeslotColumns());
    }

    function renderHourBatch() {
        const pageSize = timeslotPageSize();
        const nextCount =
            pageSize > 0 ? Math.min(pendingHourButtons.length, renderedHourCount + pageSize) : pendingHourButtons.length;

        for (let i = renderedHourCount; i < nextCount; i++) {
            $availableHours.append(pendingHourButtons[i]);
        }

        renderedHourCount = nextCount;
        const hasMore = renderedHourCount < pendingHourButtons.length;
        $availableHoursMore.prop('hidden', !hasMore);
    }

    /**
     * Get Available Hours
     *
     * This function makes an AJAX call and returns the available hours for the selected service,
     * provider and date.
     *
     * @param {String} selectedDate The selected date of the available hours we need.
     */
    function getAvailableHours(selectedDate) {
        $availableHours.empty();
        $availableHoursMore.prop('hidden', true);
        pendingHourButtons = [];
        renderedHourCount = 0;
        applyTimeslotLayoutClasses();

        // Find the selected service duration (it is going to be send within the "data" object).
        const serviceId = $selectService.val();

        // Default value of duration (in minutes).
        let serviceDuration = 15;

        const service = vars('available_services').find(
            (availableService) => Number(availableService.id) === Number(serviceId),
        );

        if (service) {
            serviceDuration = service.duration;
        }

        // If the manage mode is true then the appointment's start date should return as available too.
        const appointmentId = vars('manage_mode') ? vars('appointment_data').id : null;

        // Make ajax post request and get the available hours.
        const url = App.Utils.Url.siteUrl('booking/get_available_hours');

        const data = {
            csrf_token: vars('csrf_token'),
            service_id: $selectService.val(),
            provider_id: $selectProvider.val(),
            selected_date: selectedDate,
            service_duration: serviceDuration,
            manage_mode: Number(vars('manage_mode') || 0),
            appointment_id: appointmentId,
        };

        $.post(url, data).done((response) => {
            $availableHours.empty();
            pendingHourButtons = [];
            renderedHourCount = 0;
            applyTimeslotLayoutClasses();

            // The response contains the available hours for the selected provider and service. Fill the available
            // hours div with response data.
            if (response.length > 0) {
                let providerId = $selectProvider.val();

                if (providerId === 'any-provider') {
                    for (const availableProvider of vars('available_providers')) {
                        if (availableProvider.services.indexOf(Number(serviceId)) !== -1) {
                            providerId = availableProvider.id; // Use first available provider.
                            break;
                        }
                    }
                }

                const provider = vars('available_providers').find(
                    (availableProvider) => Number(providerId) === Number(availableProvider.id),
                );

                if (!provider) {
                    throw new Error('Could not find provider.');
                }

                const providerTimezone = provider.timezone;
                const selectedTimezone = $('#select-timezone').val();
                const timeFormat = vars('time_format') === 'regular' ? 'h:mm a' : 'HH:mm';

                response.forEach((availableHour) => {
                    const availableHourMoment = moment
                        .tz(selectedDate + ' ' + availableHour + ':00', providerTimezone)
                        .tz(selectedTimezone);

                    if (availableHourMoment.format('YYYY-MM-DD') !== selectedDate) {
                        return; // Due to the selected timezone the available hour belongs to another date.
                    }

                    pendingHourButtons.push(
                        $('<button/>', {
                            'type': 'button',
                            'class': 'btn btn-outline-secondary w-100 shadow-none available-hour',
                            'data': {
                                'value': availableHour,
                            },
                            'text': availableHourMoment.format(timeFormat),
                        }),
                    );
                });

                renderHourBatch();

                if (App.Pages.Booking.manageMode) {
                    // Set the appointment's start time as the default selection.
                    $('.available-hour')
                        .removeClass('selected-hour')
                        .filter(
                            (index, availableHourEl) =>
                                $(availableHourEl).text() ===
                                moment(vars('appointment_data').start_datetime).format(timeFormat),
                        )
                        .addClass('selected-hour');
                } else {
                    // Set the first available hour as the default selection.
                    $('.available-hour:eq(0)').addClass('selected-hour');
                }

                App.Pages.Booking.updateConfirmFrame();
            }

            if (!$availableHours.find('.available-hour').length) {
                $availableHours.text(lang('no_available_hours'));
                $availableHoursMore.prop('hidden', true);
            }
        });
    }

    /**
     * Register an appointment to the database.
     *
     * This method will make an ajax call to the appointments controller that will register
     * the appointment to the database.
     */
    function registerAppointment() {
        const $captchaText = $('.captcha-text');
        const $altchaPayload = $('#altcha-payload');
        const $altchaHint = $('#altcha-hint');
        const skipConfirmation = Boolean(vars('booking_skip_confirmation_step'));

        // CAPTCHA/ALTCHA live on the confirmation step. When that step is skipped,
        // do not block registration on empty captcha fields (server skips too).
        if (!skipConfirmation) {
            if ($captchaText.length > 0) {
                $captchaText.removeClass('is-invalid');
                if ($captchaText.val() === '') {
                    $captchaText.addClass('is-invalid');
                    if (App.Pages?.Booking?.trackBookingProgress) {
                        App.Pages.Booking.trackBookingProgress('register_blocked_captcha_empty');
                    }
                    return;
                }
            }

            if ($altchaPayload.length > 0 && $altchaPayload.val() === '') {
                $altchaHint.text(lang('altcha_verification_failed')).fadeTo(400, 1);

                setTimeout(() => {
                    $altchaHint.fadeTo(400, 0);
                }, 3000);

                if (App.Pages?.Booking?.trackBookingProgress) {
                    App.Pages.Booking.trackBookingProgress('register_blocked_altcha_empty');
                }

                return;
            }
        }

        let formData;

        try {
            formData = JSON.parse($('input[name="post_data"]').val() || '');
        } catch (error) {
            if (App.Pages?.Booking?.trackBookingProgress) {
                App.Pages.Booking.trackBookingProgress('register_blocked_invalid_post_data');
            }
            alert(lang('unexpected_issues_occurred'));
            return;
        }

        if (!formData || typeof formData !== 'object') {
            if (App.Pages?.Booking?.trackBookingProgress) {
                App.Pages.Booking.trackBookingProgress('register_blocked_missing_post_data');
            }
            return;
        }

        const data = {
            csrf_token: vars('csrf_token'),
            post_data: formData,
        };

        if (!skipConfirmation && $captchaText.length > 0) {
            data.captcha = $captchaText.val();
        }

        if (!skipConfirmation && $altchaPayload.length > 0 && $altchaPayload.val()) {
            data.altcha_payload = $altchaPayload.val();
        }

        if (vars('manage_mode')) {
            data.exclude_appointment_id = vars('appointment_data').id;
        }

        const url = App.Utils.Url.siteUrl('booking/register');

        const $layer = $('<div/>');

        $.ajax({
            url: url,
            method: 'post',
            data: data,
            dataType: 'json',
            beforeSend: () => {
                $layer.appendTo('body').css({
                    background: 'white',
                    position: 'fixed',
                    top: '0',
                    left: '0',
                    height: '100vh',
                    width: '100vw',
                    opacity: '0.5',
                });
            },
        })
            .done((response) => {
                if (response.captcha_verification === false) {
                    $captchaHint.text(lang('captcha_is_wrong')).fadeTo(400, 1);

                    setTimeout(() => {
                        $captchaHint.fadeTo(400, 0);
                    }, 3000);

                    $captchaTitle.find('button').trigger('click');

                    $captchaText.addClass('is-invalid');

                    if (App.Pages?.Booking?.trackBookingProgress) {
                        App.Pages.Booking.trackBookingProgress('register_failed_captcha');
                    }

                    return false;
                }

                if (response.altcha_verification === false) {
                    $altchaHint.text(lang('altcha_verification_failed')).fadeTo(400, 1);

                    setTimeout(() => {
                        $altchaHint.fadeTo(400, 0);
                    }, 3000);

                    // Reset ALTCHA widget
                    if (App.Utils.Altcha) {
                        App.Utils.Altcha.reset('altcha-widget');
                    }

                    if (App.Pages?.Booking?.trackBookingProgress) {
                        App.Pages.Booking.trackBookingProgress('register_failed_altcha');
                    }

                    return false;
                }

                if (App.Pages?.Booking?.trackBookingProgress) {
                    App.Pages.Booking.trackBookingProgress('booking_completed', {
                        appointment_id: response.appointment_id || null,
                        appointment_hash: response.appointment_hash || null,
                    });
                }

                if (window.App?.BookingEvents?.onBooked) {
                    window.App.BookingEvents.onBooked(response);
                }

                if (typeof window.eaBookingConversion === 'function') {
                    window.eaBookingConversion(response);
                }

                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                    return;
                }

                window.location.href = App.Utils.Url.siteUrl('booking_confirmation/of/' + response.appointment_hash);
            })
            .fail((jqXHR) => {
                $captchaTitle.find('button').trigger('click');

                const message =
                    jqXHR.responseJSON?.message ||
                    jqXHR.responseText ||
                    lang('service_communication_error');

                if (App.Pages?.Booking?.trackBookingProgress) {
                    App.Pages.Booking.trackBookingProgress('register_failed', {
                        status: jqXHR.status,
                        message,
                    });
                }

                alert(message);
            })
            .always(() => {
                $layer.remove();
            });
    }

    /**
     * Get the unavailable dates of a provider.
     *
     * This method will fetch the unavailable dates of the selected provider and service and then it will
     * select the first available date (if any). It uses the "FrontendBookApi.getAvailableHours" method to
     * fetch the appointment* hours of the selected date.
     *
     * @param {Number} providerId The selected provider ID.
     * @param {Number} serviceId The selected service ID.
     * @param {String} selectedDateString Y-m-d value of the selected date.
     * @param {Number} [monthChangeStep] Whether to add or subtract months.
     */
    function getUnavailableDates(providerId, serviceId, selectedDateString, monthChangeStep = 1) {
        if (processingUnavailableDates) {
            return;
        }

        if (!providerId || !serviceId) {
            return;
        }

        const appointmentId = App.Pages.Booking.manageMode ? vars('appointment_data').id : null;

        const url = App.Utils.Url.siteUrl('booking/get_unavailable_dates');

        const data = {
            provider_id: providerId,
            service_id: serviceId,
            selected_date: encodeURIComponent(selectedDateString),
            csrf_token: vars('csrf_token'),
            manage_mode: Number(App.Pages.Booking.manageMode),
            appointment_id: appointmentId,
        };

        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            dataType: 'json',
        })
            .done((response) => {
                // In case the current month has no availability, the app will try the next one or the one after in order to
                // find a date that has at least one slot

                if (response.is_month_unavailable) {
                    if (!searchedMonthStart) {
                        searchedMonthStart = selectedDateString;
                    }

                    if (searchedMonthCounter >= MONTH_SEARCH_LIMIT) {
                        // Need to mark the current month dates as unavailable
                        const selectedDateMoment = moment(searchedMonthStart);
                        const startOfMonthMoment = selectedDateMoment.clone().startOf('month');
                        const endOfMonthMoment = selectedDateMoment.clone().endOf('month');
                        const unavailableDates = [];

                        while (startOfMonthMoment.isSameOrBefore(endOfMonthMoment)) {
                            unavailableDates.push(startOfMonthMoment.format('YYYY-MM-DD'));
                            startOfMonthMoment.add(Math.abs(monthChangeStep), 'days'); // Move to the next day
                        }

                        applyUnavailableDates(unavailableDates, searchedMonthStart, true);
                        searchedMonthStart = undefined;
                        searchedMonthCounter = 0;

                        return; // Stop searching
                    }

                    searchedMonthCounter++;

                    const selectedDateMoment = moment(selectedDateString);
                    selectedDateMoment.add(1, 'month');

                    const nextSelectedDate = selectedDateMoment.format('YYYY-MM-DD');
                    getUnavailableDates(providerId, serviceId, nextSelectedDate, monthChangeStep);

                    return;
                }

                unavailableDatesBackup = response;
                selectedDateStringBackup = selectedDateString;
                applyUnavailableDates(response, selectedDateString, true);
            })
            .fail(() => {
                $selectDate.parent().fadeTo(400, 1);
            });
    }

    function applyPreviousUnavailableDates() {
        applyUnavailableDates(unavailableDatesBackup, selectedDateStringBackup);
    }

    function applyUnavailableDates(unavailableDates, selectedDateString, setDate) {
        setDate = setDate || false;

        $selectDate.parent().fadeTo(400, 1);

        processingUnavailableDates = true;

        // Select first enabled date.
        const selectedDateMoment = moment(selectedDateString);
        const selectedDate = selectedDateMoment.toDate();
        const numberOfDays = selectedDateMoment.daysInMonth();

        // If all the days are unavailable then hide the appointments hours.
        if (unavailableDates.length === numberOfDays) {
            $availableHours.text(lang('no_available_hours'));
        }

        // Grey out unavailable dates.
        $selectDate[0]._flatpickr.set(
            'disable',
            unavailableDates.map((unavailableDate) => new Date(unavailableDate + 'T00:00')),
        );

        if (setDate && !vars('manage_mode')) {
            for (let i = 1; i <= numberOfDays; i++) {
                const currentDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), i);

                if (unavailableDates.indexOf(moment(currentDate).format('YYYY-MM-DD')) === -1) {
                    App.Utils.UI.setDateTimePickerValue($selectDate, currentDate);
                    getAvailableHours(moment(currentDate).format('YYYY-MM-DD'));
                    break;
                }
            }
        }

        const dateQueryParam = App.Utils.Url.queryParam('date');

        if (dateQueryParam) {
            const dateQueryParamMoment = moment(dateQueryParam);

            if (
                dateQueryParamMoment.isValid() &&
                !unavailableDates.includes(dateQueryParam) &&
                dateQueryParamMoment.format('YYYY-MM') === selectedDateMoment.format('YYYY-MM')
            ) {
                App.Utils.UI.setDateTimePickerValue($selectDate, dateQueryParamMoment.toDate());
            }
        }

        searchedMonthStart = undefined;
        searchedMonthCounter = 0;
        processingUnavailableDates = false;
    }

    /**
     * Delete personal information.
     *
     * @param {Number} customerToken Customer unique token.
     */
    function deletePersonalInformation(customerToken) {
        const url = App.Utils.Url.siteUrl('privacy/delete_personal_information');

        const data = {
            csrf_token: vars('csrf_token'),
            customer_token: customerToken,
        };

        $.post(url, data).done(() => {
            window.location.href = vars('base_url');
        });
    }

    return {
        registerAppointment,
        getAvailableHours,
        getUnavailableDates,
        applyPreviousUnavailableDates,
        deletePersonalInformation,
        renderHourBatch,
    };
})();

$(() => {
    $('#load-more-hours').on('click', () => {
        App.Http.Booking.renderHourBatch();
    });
});
