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
 * Booking page.
 *
 * This module implements the functionality of the booking page
 *
 * Old Name: FrontendBook
 */
App.Pages.Booking = (function () {
    const $selectDate = $('#select-date');
    const $selectService = $('#select-service');
    const $selectProvider = $('#select-provider');
    const $selectTimezone = $('#select-timezone');
    const $firstName = $('#first-name');
    const $lastName = $('#last-name');
    const $email = $('#email');
    const $phoneNumber = $('#phone-number');
    const $address = $('#address');
    const $city = $('#city');
    const $zipCode = $('#zip-code');
    const $notes = $('#notes');
    const $captchaTitle = $('.captcha-title');
    const $availableHours = $('#available-hours');
    const $bookAppointmentSubmit = $('#book-appointment-submit');
    const $deletePersonalInformation = $('#delete-personal-information');
    const $displayBookingSelection = $('.display-booking-selection');
    const $rememberMe = $('#remember-me');
    const tippy = window.tippy;
    const moment = window.moment;

    const STORAGE_KEY = 'EasyAppointments.CustomerInfo';

    /**
     * Determines the functionality of the page.
     *
     * @type {Boolean}
     */
    let manageMode = vars('manage_mode') || false;

    /**
     * Parsed URL query data (customer / UTM / custom questions kept separate).
     *
     * @type {{customer: Object, utm: Object, customQuestions: Object}}
     */
    let urlParamsData = {customer: {}, utm: {}, customQuestions: {}};

    /**
     * When true, skip the customer information step after time selection.
     *
     * @type {Boolean}
     */
    let skipCustomerStep = false;

    window.App = window.App || {};
    window.App.BookingEvents = window.App.BookingEvents || {
        onBooked(payload) {
            // Hook for integrations; overridden or extended by conversion scripts.
        },
    };

    /**
     * Active custom fields count from settings.
     *
     * @returns {Number}
     */
    function getCustomFieldCount() {
        return Number(vars('custom_fields_count') || 5);
    }

    /**
     * Read a custom field value by index.
     *
     * @param {Number} i
     *
     * @returns {String}
     */
    function getCustomFieldValue(i) {
        return $('#custom-field-' + i).val();
    }

    /**
     * Write a custom field value by index.
     *
     * @param {Number} i
     * @param {String} value
     */
    function setCustomFieldValue(i, value) {
        $('#custom-field-' + i).val(value || '');
    }

    /**
     * Collect custom field values into an object keyed by custom_field_N.
     *
     * @returns {Object}
     */
    function collectCustomFieldValues() {
        const values = {};

        for (let i = 1; i <= getCustomFieldCount(); i++) {
            values['custom_field_' + i] = getCustomFieldValue(i);
        }

        return values;
    }

    /**
     * Detect the month step.
     *
     * @param previousDateTimeMoment
     * @param nextDateTimeMoment
     *
     * @returns {Number}
     */
    function detectDatepickerMonthChangeStep(previousDateTimeMoment, nextDateTimeMoment) {
        return previousDateTimeMoment.isAfter(nextDateTimeMoment) ? -1 : 1;
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        if (Boolean(Number(vars('display_cookie_notice'))) && window?.cookieconsent) {
            cookieconsent.initialise({
                palette: {
                    popup: {
                        background: '#ffffffbd',
                        text: '#666666',
                    },
                    button: {
                        background: '#429a82',
                        text: '#ffffff',
                    },
                },
                content: {
                    message: lang('website_using_cookies_to_ensure_best_experience'),
                    dismiss: 'OK',
                },
            });

            const $cookieNoticeLink = $('.cc-link');

            $cookieNoticeLink.replaceWith(
                $('<a/>', {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#cookie-notice-modal',
                    'href': '#',
                    'class': 'cc-link',
                    'text': $cookieNoticeLink.text(),
                }),
            );
        }

        manageMode = vars('manage_mode');

        // Initialize page's components (tooltips, date pickers etc).
        tippy('[data-tippy-content]');

        let monthTimeout;

        App.Utils.UI.initializeDatePicker($selectDate, {
            inline: true,
            minDate: moment().subtract(1, 'day').set({hours: 23, minutes: 59, seconds: 59}).toDate(),
            maxDate: moment().add(vars('future_booking_limit'), 'days').toDate(),
            onChange: (selectedDates) => {
                App.Http.Booking.getAvailableHours(moment(selectedDates[0]).format('YYYY-MM-DD'));
                App.Pages.Booking.updateConfirmFrame();
                trackBookingProgress('date_selected', {
                    selected_date: moment(selectedDates[0]).format('YYYY-MM-DD'),
                });
            },

            onMonthChange: (selectedDates, dateStr, instance) => {
                $selectDate.parent().fadeTo(400, 0.3); // Change opacity during loading

                if (monthTimeout) {
                    clearTimeout(monthTimeout);
                }

                monthTimeout = setTimeout(() => {
                    const previousMoment = moment(instance.selectedDates[0]);

                    const displayedMonthMoment = moment(
                        instance.currentYearElement.value +
                            '-' +
                            String(Number(instance.monthsDropdownContainer.value) + 1).padStart(2, '0') +
                            '-01',
                    );

                    const monthChangeStep = detectDatepickerMonthChangeStep(previousMoment, displayedMonthMoment);

                    App.Http.Booking.getUnavailableDates(
                        $selectProvider.val(),
                        $selectService.val(),
                        displayedMonthMoment.format('YYYY-MM-DD'),
                        monthChangeStep,
                    );
                }, 500);
            },

            onYearChange: (selectedDates, dateStr, instance) => {
                setTimeout(() => {
                    const previousMoment = moment(instance.selectedDates[0]);

                    const displayedMonthMoment = moment(
                        instance.currentYearElement.value +
                            '-' +
                            String(Number(instance.monthsDropdownContainer.value) + 1).padStart(2, '0') +
                            '-01',
                    );

                    const monthChangeStep = detectDatepickerMonthChangeStep(previousMoment, displayedMonthMoment);

                    App.Http.Booking.getUnavailableDates(
                        $selectProvider.val(),
                        $selectService.val(),
                        displayedMonthMoment.format('YYYY-MM-DD'),
                        monthChangeStep,
                    );
                }, 500);
            },
        });

        App.Utils.UI.setDateTimePickerValue($selectDate, new Date());

        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const isTimezoneSupported = $selectTimezone.find(`option[value="${browserTimezone}"]`).length > 0;
        $selectTimezone.val(isTimezoneSupported ? browserTimezone : 'UTC');

        // Bind the event handlers (might not be necessary every time we use this class).
        addEventListeners();

        optimizeContactInfoDisplay();

        const serviceOptionCount = $selectService.find('option').length;

        if (serviceOptionCount === 2) {
            $selectService.find('option[value=""]').remove();
            const firstServiceId = $selectService.find('option:first').attr('value');
            $selectService.val(firstServiceId).trigger('change');
        }

        // If the manage mode is true, the appointment data should be loaded by default.
        if (manageMode) {
            applyAppointmentData(vars('appointment_data'), vars('provider_data'), vars('customer_data'));

            $('#wizard-frame-1')
                .css({
                    'visibility': 'visible',
                    'display': 'none',
                })
                .fadeIn();

            applyBookingUiHiding();
            applyManageModeDateTimeOnly();
            trackBookingProgress('initialize');
        } else {
            // Check if a specific service was selected (via URL parameter).
            const selectedServiceId = App.Utils.Url.queryParam('service');

            if (selectedServiceId && $selectService.find('option[value="' + selectedServiceId + '"]').length > 0) {
                $selectService.val(selectedServiceId);
            }

            $selectService.trigger('change'); // Load the available hours.

            // Check if a specific provider was selected.
            const selectedProviderId = App.Utils.Url.queryParam('provider');

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length === 0) {
                // Select a service of this provider in order to make the provider available in the select box.
                for (const index in vars('available_providers')) {
                    const provider = vars('available_providers')[index];

                    if (Number(provider.id) === Number(selectedProviderId) && provider.services.length > 0) {
                        $selectService.val(provider.services[0]).trigger('change');
                    }
                }
            }

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length > 0) {
                $selectProvider.val(selectedProviderId).trigger('change');
            }

            if (
                (selectedServiceId && selectedProviderId) ||
                (vars('available_services').length === 1 && vars('available_providers').length === 1)
            ) {
                if (!selectedServiceId) {
                    $selectService.val(vars('available_services')[0].id).trigger('change');
                }

                if (!selectedProviderId) {
                    $selectProvider.val(vars('available_providers')[0].id).trigger('change');
                }

                $('.active-step').removeClass('active-step');
                $('#step-2').addClass('active-step');
                $('#wizard-frame-1').hide();
                $('#wizard-frame-2').fadeIn();

                $selectService.closest('.wizard-frame').find('.button-next').trigger('click');

                $('#step-1').hide().removeClass('d-inline-block');

                $(document).find('.button-back:first').css('visibility', 'hidden');

                $('#steps .book-step:visible').each((index, bookStepEl) =>
                    $(bookStepEl)
                        .find('strong')
                        .text(index + 1),
                );
            } else {
                $('#wizard-frame-1')
                    .css({
                        'visibility': 'visible',
                        'display': 'none',
                    })
                    .fadeIn();
            }

            prefillFromQueryParam('#first-name', 'first_name');
            prefillFromQueryParam('#last-name', 'last_name');
            prefillFromQueryParam('#email', 'email');
            prefillFromQueryParam('#phone-number', 'phone');
            prefillFromQueryParam('#address', 'address');
            prefillFromQueryParam('#city', 'city');
            prefillFromQueryParam('#zip-code', 'zip');

            parseUrlParamsData();
            applyUrlCustomFieldPrefills();

            // Initialize remember me after prefilling from query params
            initializeRememberMe();

            evaluateSkipCustomerStep();
            applyBookingUiHiding();
            triggerMauticLeadLookup();
            trackBookingProgress('initialize');
        }
    }

    function prefillFromQueryParam(field, param) {
        const $target = $(field);

        if (!$target.length) {
            return;
        }

        const value = App.Utils.Url.queryParam(param);

        if (value) {
            $target.val(value);
        }
    }

    /**
     * Parse URL query params into customer / UTM / customQuestions buckets (kept separate).
     */
    function parseUrlParamsData() {
        const urlParams = new URLSearchParams(window.location.search);
        const customer = {};
        const utm = {};
        const customQuestions = {};

        const customerKeys = {
            first_name: 'first_name',
            last_name: 'last_name',
            email: 'email',
            phone: 'phone',
            phone_number: 'phone_number',
            address: 'address',
            city: 'city',
            zip: 'zip',
            zip_code: 'zip_code',
        };

        urlParams.forEach((value, key) => {
            if (customerKeys[key] !== undefined) {
                customer[key] = value;
                return;
            }

            if (key.startsWith('utm_')) {
                utm[key] = value;
                return;
            }

            if (/^custom_field_\d+$/.test(key) || key.startsWith('question_')) {
                customQuestions[key] = value;
            }
        });

        urlParamsData = {customer, utm, customQuestions};
    }

    /**
     * Prefill custom_field_N inputs from URL (separate from UTM).
     */
    function applyUrlCustomFieldPrefills() {
        for (let i = 1; i <= getCustomFieldCount(); i++) {
            const key = 'custom_field_' + i;
            const value = App.Utils.Url.queryParam(key);

            if (value) {
                setCustomFieldValue(i, value);
            }
        }
    }

    /**
     * Decide whether the customer step can be skipped.
     */
    function evaluateSkipCustomerStep() {
        const firstName = ($firstName.val() || '').trim();
        const lastName = ($lastName.val() || '').trim();
        const email = ($email.val() || '').trim();
        const phone = ($phoneNumber.val() || '').trim();

        if (!firstName || !lastName || !email || !phone) {
            skipCustomerStep = false;
            return;
        }

        if (!App.Utils.Validation.email(email)) {
            skipCustomerStep = false;
            return;
        }

        if (App.Utils.Validation.phone && !App.Utils.Validation.phone(phone)) {
            skipCustomerStep = false;
            return;
        }

        skipCustomerStep = true;

        if (skipCustomerStep) {
            $('#step-3').hide();
        }
    }

    /**
     * Apply booking UI hide flags from settings.
     */
    function applyBookingUiHiding() {
        if (vars('hide_booking_timezone_selector')) {
            $('#select-timezone-group').addClass('hidden').hide();
            $selectTimezone.closest('.mb-3').addClass('hidden').hide();
        }

        if (vars('hide_booking_custom_fields')) {
            $('#booking-custom-fields').addClass('hidden').hide();
        }

        updateProviderVisibility();
    }

    /**
     * In manage/reschedule mode, optionally lock service + provider so customers
     * can only change date/time.
     */
    function applyManageModeDateTimeOnly() {
        if (!manageMode || !vars('booking_manage_date_time_only')) {
            return;
        }

        $selectService.prop('disabled', true);
        $selectProvider.prop('disabled', true);

        // Jump straight to the date/time step; do not allow going back to service/provider.
        $('.active-step').removeClass('active-step');
        $('#step-2').addClass('active-step');
        $('#wizard-frame-1').hide();
        $('#wizard-frame-2').fadeIn();
        $('#step-1').hide().removeClass('d-inline-block');
        $('#wizard-frame-2 .button-back').css('visibility', 'hidden');

        $('#steps .book-step:visible').each((index, bookStepEl) =>
            $(bookStepEl)
                .find('strong')
                .text(index + 1),
        );

        // Ensure hours load for the locked service/provider.
        $selectDate.trigger('change');
    }

    /**
     * Hide provider select when only one real provider remains (optional setting).
     */
    function updateProviderVisibility() {
        if (!vars('hide_booking_single_provider')) {
            return;
        }

        const $group = $('#select-provider-group');
        const realOptions = $selectProvider.find('option').filter(function () {
            const value = $(this).attr('value');
            return value && value !== '' && value !== 'any-provider';
        });

        if (realOptions.length <= 1) {
            $group.addClass('hidden').hide();
        } else {
            $group.removeClass('hidden');
        }
    }

    /**
     * Fire Mautic lead lookup using the internal Mautic lead id from the URL (`l_id`).
     * Response fields are applied to the customer form when present.
     */
    function triggerMauticLeadLookup() {
        if (!vars('mautic_lead_lookup_enabled')) {
            return;
        }

        const lId = App.Utils.Url.queryParam('l_id');

        if (!lId) {
            return;
        }

        // Prefer the same-origin proxy to avoid CORS restrictions on the external webhook.
        const url = App.Utils.Url.siteUrl('booking/mautic_lookup') + '?l_id=' + encodeURIComponent(lId);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data || typeof data !== 'object') {
                    return;
                }

                applyMauticLeadData(data);
                evaluateSkipCustomerStep();
                trackBookingProgress('mautic_lead_lookup', {l_id: lId, lead: data});
            })
            .catch(() => {});
    }

    /**
     * Apply Mautic lead lookup fields onto the customer form.
     *
     * @param {Object} lead
     */
    function applyMauticLeadData(lead) {
        if (lead.first_name && !$firstName.val()) {
            $firstName.val(lead.first_name);
        }

        if (lead.last_name && !$lastName.val()) {
            $lastName.val(lead.last_name);
        }

        if (lead.email && !$email.val()) {
            $email.val(lead.email);
        }

        const phone = lead.phone_number || lead.phone || lead.mobile;

        if (phone && !$phoneNumber.val()) {
            $phoneNumber.val(phone);
        }

        updateConfirmFrame();
    }

    /**
     * Post booking funnel progress when tracking is enabled.
     *
     * @param {String} step
     * @param {Object} [extra]
     */
    let trackDebounceTimer = null;

    function trackBookingProgress(step, extra = {}) {
        if (!vars('booking_tracking_enabled')) {
            return;
        }

        const selectedDateObject = App.Utils.UI.getDateTimePickerValue($selectDate);
        const selectedHour = $('.selected-hour').data('value');

        let selectedDatetime = null;

        if (selectedDateObject && selectedHour) {
            selectedDatetime =
                moment(selectedDateObject).format('YYYY-MM-DD') +
                ' ' +
                moment(selectedHour, 'HH:mm').format('HH:mm') +
                ':00';
        }

        const payload = Object.assign(
            {
                step,
                customer: {
                    first_name: $firstName.val(),
                    last_name: $lastName.val(),
                    email: $email.val(),
                    phone_number: $phoneNumber.val(),
                    ...collectCustomFieldValues(),
                },
                utm: urlParamsData.utm || {},
                customQuestions: urlParamsData.customQuestions || {},
                service_id: $selectService.val() || null,
                provider_id: $selectProvider.val() || null,
                selected_datetime: selectedDatetime,
            },
            extra,
        );

        const headers = {
            'Content-Type': 'application/json',
        };

        if (vars('csrf_token')) {
            headers['X-CSRF'] = vars('csrf_token');
            payload.csrf_token = vars('csrf_token');
        }

        fetch(App.Utils.Url.siteUrl('booking/track'), {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        }).catch(() => {});
    }

    /**
     * Debounced tracking for field-level interactions.
     *
     * @param {String} step
     * @param {Object} [extra]
     */
    function trackBookingProgressDebounced(step, extra = {}) {
        if (!vars('booking_tracking_enabled')) {
            return;
        }

        clearTimeout(trackDebounceTimer);
        trackDebounceTimer = setTimeout(() => {
            trackBookingProgress(step, extra);
        }, 400);
    }

    /**
     * Submit the booking without showing the confirmation step.
     */
    function submitBookingWithoutConfirmation() {
        App.Pages.Booking.updateConfirmFrame();
        trackBookingProgress('skip_confirmation_submit');
        App.Http.Booking.registerAppointment();
    }

    /**
     * Remove empty columns and center elements if needed.
     */
    function optimizeContactInfoDisplay() {
        // If a column has only one control shown then move the control to the other column.

        const $firstCol = $('#wizard-frame-3 .field-col:first');
        const $firstColControls = $firstCol.find('.form-control');
        const $secondCol = $('#wizard-frame-3 .field-col:last');
        const $secondColControls = $secondCol.find('.form-control');

        if ($firstColControls.length === 1 && $secondColControls.length > 1) {
            $firstColControls.each((index, controlEl) => {
                $(controlEl).parent().insertBefore($secondColControls.first().parent());
            });
        }

        if ($secondColControls.length === 1 && $firstColControls.length > 1) {
            $secondColControls.each((index, controlEl) => {
                $(controlEl).parent().insertAfter($firstColControls.last().parent());
            });
        }

        // Hide columns that do not have any controls displayed.

        const $fieldCols = $(document).find('#wizard-frame-3 .field-col');

        $fieldCols.each((index, fieldColEl) => {
            const $fieldCol = $(fieldColEl);

            if (!$fieldCol.find('.form-control').length) {
                $fieldCol.hide();
            }
        });
    }

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Timezone "Changed"
         */
        $selectTimezone.on('change', () => {
            const date = App.Utils.UI.getDateTimePickerValue($selectDate);

            if (!date) {
                return;
            }

            App.Http.Booking.getAvailableHours(moment(date).format('YYYY-MM-DD'));

            App.Pages.Booking.updateConfirmFrame();
        });

        /**
         * Event: Selected Provider "Changed"
         *
         * Whenever the provider changes the available appointment date - time periods must be updated.
         */
        $selectProvider.on('change', () => {
            App.Pages.Booking.updateConfirmFrame();
        });

        /**
         * Event: Selected Service "Changed"
         *
         * When the user clicks on a service, its available providers should
         * become visible.
         */
        $selectService.on('change', (event) => {
            const $target = $(event.target);
            const serviceId = $selectService.val();
            const previousProviderId = $selectProvider.val();

            $selectProvider.parent().prop('hidden', !Boolean(serviceId));

            $selectProvider.empty();

            $selectProvider.append(new Option(lang('please_select'), ''));

            let previousProviderCanServe = false;

            vars('available_providers').forEach((provider) => {
                // If the current provider is able to provide the selected service, add him to the list box.
                const canServeService =
                    provider.services.filter((providerServiceId) => Number(providerServiceId) === Number(serviceId))
                        .length > 0;

                if (canServeService) {
                    $selectProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));

                    if (String(provider.id) === String(previousProviderId)) {
                        previousProviderCanServe = true;
                    }
                }
            });

            const providerOptionCount = $selectProvider.find('option').length;

            // Remove the "Please Select" option, if there is only one provider available

            if (providerOptionCount === 2) {
                $selectProvider.find('option[value=""]').remove();
            }

            // Add the "Any Provider" entry

            if (providerOptionCount > 2 && Boolean(Number(vars('display_any_provider')))) {
                $(new Option(lang('any_provider'), 'any-provider')).insertAfter($selectProvider.find('option:first'));
            }

            // Restore previous provider selection if they can serve the new service
            if (previousProviderId && previousProviderCanServe) {
                $selectProvider.val(previousProviderId);
            } else if (previousProviderId === 'any-provider' && providerOptionCount > 2 && Boolean(Number(vars('display_any_provider')))) {
                $selectProvider.val('any-provider');
            }

            updateProviderVisibility();

            App.Pages.Booking.updateConfirmFrame();

            App.Pages.Booking.updateServiceDescription(serviceId);
        });

        /**
         * Event: Next Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "next" button on the book wizard.
         * Some special tasks might be performed, depending on the current wizard step.
         */
        $('.button-next').on('click', (event) => {
            const $target = $(event.currentTarget);

            // If we are on the first step and there is no provider selected do not continue with the next step.
            if ($target.attr('data-step_index') === '1' && !$selectProvider.val()) {
                return;
            }

            // If we are on the first step, fetch unavailable dates and available hours for step 2.
            if ($target.attr('data-step_index') === '1') {
                const todayMoment = moment();

                App.Utils.UI.setDateTimePickerValue($selectDate, todayMoment.toDate());

                App.Http.Booking.getUnavailableDates(
                    $selectProvider.val(),
                    $selectService.val(),
                    todayMoment.format('YYYY-MM-DD'),
                );
            }

            // If we are on the 2nd tab then the user should have an appointment hour selected.
            if ($target.attr('data-step_index') === '2') {
                if (!$('.selected-hour').length) {
                    if (!$('#select-hour-prompt').length) {
                        $('<div/>', {
                            'id': 'select-hour-prompt',
                            'class': 'text-danger mb-4',
                            'text': lang('appointment_hour_missing'),
                        }).prependTo('#available-hours');
                    }
                    return;
                }
            }

            // If we are on the 3rd tab then we will need to validate the user's input before proceeding to the next
            // step.
            if ($target.attr('data-step_index') === '3') {
                if (!App.Pages.Booking.validateCustomerForm()) {
                    return; // Validation failed, do not continue.
                }

                App.Pages.Booking.updateConfirmFrame();
                triggerMauticLeadLookup();

                if (vars('booking_skip_confirmation_step')) {
                    submitBookingWithoutConfirmation();
                    return;
                }

                // Initialize ALTCHA widget if present
                if ($('#altcha-widget').length && App.Utils.Altcha) {
                    App.Utils.Altcha.initialize('altcha-widget');
                }
            }

            // Display the next step tab (uses jquery animation effect).
            let nextTabIndex = parseInt($target.attr('data-step_index')) + 1;

            // Skip customer step when contact details were prefilled and valid.
            if ($target.attr('data-step_index') === '2' && skipCustomerStep) {
                if (!App.Pages.Booking.validateCustomerForm()) {
                    skipCustomerStep = false;
                    $('#step-3').show();
                } else {
                    App.Pages.Booking.updateConfirmFrame();
                    triggerMauticLeadLookup();
                    $('#step-3').hide();

                    if (vars('booking_skip_confirmation_step')) {
                        submitBookingWithoutConfirmation();
                        return;
                    }

                    nextTabIndex = 4;

                    if ($('#altcha-widget').length && App.Utils.Altcha) {
                        App.Utils.Altcha.initialize('altcha-widget');
                    }
                }
            }

            // Update step indicator immediately
            $('.active-step').removeClass('active-step');
            $('#step-' + nextTabIndex).addClass('active-step');

            trackBookingProgress('step_' + nextTabIndex);

            $target
                .parents()
                .eq(1)
                .fadeOut(() => {
                    $('#wizard-frame-' + nextTabIndex).fadeIn();
                });

            // Scroll to the top of the page. On a small screen, especially on a mobile device, this is very useful.
            const scrollingElement = document.scrollingElement || document.body;
            if (window.innerHeight < scrollingElement.scrollHeight) {
                scrollingElement.scrollTop = 0;
            }
        });

        /**
         * Event: Back Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "back" button on the
         * book wizard.
         */
        $('.button-back').on('click', (event) => {
            let prevTabIndex = parseInt($(event.currentTarget).attr('data-step_index')) - 1;

            if ($(event.currentTarget).attr('data-step_index') === '4' && skipCustomerStep) {
                prevTabIndex = 2;
            }

            // Update step indicator immediately
            $('.active-step').removeClass('active-step');
            $('#step-' + prevTabIndex).addClass('active-step');

            trackBookingProgress('step_' + prevTabIndex);

            $(event.currentTarget)
                .parents()
                .eq(1)
                .fadeOut(() => {
                    $('#wizard-frame-' + prevTabIndex).fadeIn();
                });
        });

        /**
         * Event: Available Hour "Click"
         *
         * Triggered whenever the user clicks on an available hour for his appointment.
         */
        $availableHours.on('click', '.available-hour', (event) => {
            $availableHours.find('.selected-hour').removeClass('selected-hour');
            $(event.target).addClass('selected-hour');
            App.Pages.Booking.updateConfirmFrame();
            trackBookingProgress('time_selected');
        });

        // Track every meaningful booking interaction (not only step changes).
        $selectService.on('change.tracking', () => trackBookingProgress('service_selected'));
        $selectProvider.on('change.tracking', () => trackBookingProgress('provider_selected'));
        $('#wizard-frame-3')
            .find('input, textarea, select')
            .on('input.tracking change.tracking', (event) => {
                const field = $(event.currentTarget).attr('id') || $(event.currentTarget).attr('name') || 'field';
                trackBookingProgressDebounced('field_changed', {field});
            });

        if (manageMode) {
            /**
             * Event: Cancel Appointment Button "Click"
             *
             * Soft-cancels the appointment (status Cancelled). Requires a cancellation reason.
             *
             * @param {jQuery.Event} event
             */
            $('#cancel-appointment').on('click', () => {
                const $cancelAppointmentForm = $('#cancel-appointment-form');

                let $cancellationReason;

                const buttons = [
                    {
                        text: lang('close'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                        },
                    },
                    {
                        text: lang('confirm'),
                        click: () => {
                            if ($cancellationReason.val() === '') {
                                $cancellationReason.css('border', '2px solid #DC3545');
                                return;
                            }
                            $cancelAppointmentForm.find('#hidden-cancellation-reason').val($cancellationReason.val());
                            $cancelAppointmentForm.submit();
                        },
                    },
                ];

                App.Utils.Message.show(
                    lang('cancel_appointment_title'),
                    lang('write_appointment_removal_reason'),
                    buttons,
                );

                $cancellationReason = $('<textarea/>', {
                    'class': 'form-control mt-2',
                    'id': 'cancellation-reason',
                    'rows': '3',
                    'css': {
                        'width': '100%',
                    },
                }).appendTo('#message-modal .modal-body');

                return false;
            });

            $deletePersonalInformation.on('click', () => {
                const buttons = [
                    {
                        text: lang('cancel'),
                        click: (event, messageModal) => {
                            messageModal.hide();
                        },
                    },
                    {
                        text: lang('delete'),
                        click: () => {
                            App.Http.Booking.deletePersonalInformation(vars('customer_token'));
                        },
                    },
                ];

                App.Utils.Message.show(
                    lang('delete_personal_information'),
                    lang('delete_personal_information_prompt'),
                    buttons,
                );
            });
        }

        /**
         * Event: Book Appointment Form "Submit"
         *
         * Before the form is submitted to the server we need to make sure that in the meantime the selected appointment
         * date/time wasn't reserved by another customer or event.
         *
         * @param {jQuery.Event} event
         */
        $bookAppointmentSubmit.on('click', () => {
            const $acceptToTermsAndConditions = $('#accept-to-terms-and-conditions');

            $acceptToTermsAndConditions.removeClass('is-invalid');

            if ($acceptToTermsAndConditions.length && !$acceptToTermsAndConditions.prop('checked')) {
                $acceptToTermsAndConditions.addClass('is-invalid');
                return;
            }

            const $acceptToPrivacyPolicy = $('#accept-to-privacy-policy');

            $acceptToPrivacyPolicy.removeClass('is-invalid');

            if ($acceptToPrivacyPolicy.length && !$acceptToPrivacyPolicy.prop('checked')) {
                $acceptToPrivacyPolicy.addClass('is-invalid');
                return;
            }

            trackBookingProgress('before_register');
            triggerMauticLeadLookup();
            App.Http.Booking.registerAppointment();
        });

        /**
         * Event: Refresh captcha image.
         */
        $captchaTitle.on('click', 'button', () => {
            $('.captcha-image').attr('src', App.Utils.Url.siteUrl('captcha?' + Date.now()));
        });

        $selectDate.on('mousedown', '.ui-datepicker-calendar td', () => {
            setTimeout(() => {
                App.Http.Booking.applyPreviousUnavailableDates();
            }, 300);
        });
    }

    /**
     * This function validates the customer's data input. The user cannot continue without passing all the validation
     * checks.
     *
     * @return {Boolean} Returns the validation result.
     */
    function validateCustomerForm() {
        $('#wizard-frame-3 .is-invalid').removeClass('is-invalid');
        $('#wizard-frame-3 label.text-danger').removeClass('text-danger');

        // Validate required fields.
        let missingRequiredField = false;

        $('.required').each((index, requiredField) => {
            if (!$(requiredField).val()) {
                $(requiredField).addClass('is-invalid');
                missingRequiredField = true;
            }
        });

        if (missingRequiredField) {
            $('#form-message').text(lang('fields_are_required'));
            return false;
        }

        // Validate email address.
        if ($email.val() && !App.Utils.Validation.email($email.val())) {
            $email.addClass('is-invalid');
            $('#form-message').text(lang('invalid_email'));
            return false;
        }

        // Validate phone number.
        const phoneNumber = $phoneNumber.val();

        if (phoneNumber && !App.Utils.Validation.phone(phoneNumber)) {
            $phoneNumber.addClass('is-invalid');
            $('#form-message').text(lang('invalid_phone'));
            return false;
        }

        return true;
    }

    /**
     * Every time this function is executed, it updates the confirmation page with the latest
     * customer settings and input for the appointment booking.
     */
    function updateConfirmFrame() {
        const serviceId = $selectService.val();
        const providerId = $selectProvider.val();

        $displayBookingSelection.text(`${lang('service')} │ ${lang('provider')}`); // Notice: "│" is a custom ASCII char

        const serviceOptionText = serviceId ? $selectService.find('option:selected').text() : lang('service');
        const providerOptionText = providerId ? $selectProvider.find('option:selected').text() : lang('provider');

        if (serviceId || providerId) {
            $displayBookingSelection.text(`${serviceOptionText} │ ${providerOptionText}`);
        }

        if (!$availableHours.find('.selected-hour').text()) {
            return; // No time is selected, skip the rest of this function...
        }

        // Render the appointment details

        const service = vars('available_services').find(
            (availableService) => Number(availableService.id) === Number(serviceId),
        );

        if (!service) {
            return; // Service was not found
        }

        const selectedDateObject = App.Utils.UI.getDateTimePickerValue($selectDate);
        const selectedDateMoment = moment(selectedDateObject);
        const selectedDate = selectedDateMoment.format('YYYY-MM-DD');
        const selectedTime = $availableHours.find('.selected-hour').text();

        let formattedSelectedDate = '';

        if (selectedDateObject) {
            formattedSelectedDate =
                App.Utils.Date.format(selectedDate, vars('date_format'), vars('time_format'), false) +
                ' ' +
                selectedTime;
        }

        const timezoneOptionText = $selectTimezone.find('option:selected').text();

        $('#appointment-details').html(`
            <div>
                <div class="mb-2 fw-bold fs-3">
                    ${serviceOptionText}
                </div> 
                <div class="mb-2 fw-bold text-muted">
                    ${providerOptionText}
                </div>
                <div class="mb-2">
                    <i class="fas fa-calendar-day me-2"></i>
                    ${formattedSelectedDate}
                </div> 
                <div class="mb-2">
                    <i class="fas fa-clock me-2"></i>
                    ${service.duration} ${lang('minutes')}
                </div>
                <div class="mb-2">
                    <i class="fas fa-globe me-2"></i>
                    ${timezoneOptionText}
                </div> 
                <div class="mb-2" ${!Number(service.price) ? 'hidden' : ''}>
                    <i class="fas fa-cash-register me-2"></i>
                    ${Number(service.price).toFixed(2)} ${service.currency}
                </div>
            </div>     
        `);

        // Render the customer information

        const firstName = App.Utils.String.escapeHtml($firstName.val());
        const lastName = App.Utils.String.escapeHtml($lastName.val());
        const fullName = `${firstName} ${lastName}`.trim();
        const email = App.Utils.String.escapeHtml($email.val());
        const phoneNumber = App.Utils.String.escapeHtml($phoneNumber.val());
        const address = App.Utils.String.escapeHtml($address.val());
        const city = App.Utils.String.escapeHtml($city.val());
        const zipCode = App.Utils.String.escapeHtml($zipCode.val());

        const addressParts = [];

        if (city) {
            addressParts.push(city);
        }

        if (zipCode) {
            addressParts.push(zipCode);
        }

        $('#customer-details').html(`
            <div>
                <div class="mb-2 fw-bold fs-3">
                    ${lang('contact_info')}
                </div>
                <div class="mb-2 fw-bold text-muted" ${!fullName ? 'hidden' : ''}>
                    ${fullName}
                </div>
                <div class="mb-2" ${!email ? 'hidden' : ''}>
                    ${email}
                </div>
                <div class="mb-2" ${!phoneNumber ? 'hidden' : ''}>
                    ${phoneNumber}
                </div>
                <div class="mb-2" ${!address ? 'hidden' : ''}>
                    ${address}
                </div>
                <div class="mb-2" ${!addressParts.length ? 'hidden' : ''}>
                    ${addressParts.join(', ')}
                </div>
            </div>
        `);

        // Update appointment form data for submission to server when the user confirms the appointment.

        const data = {};

        data.customer = {
            last_name: $lastName.val(),
            first_name: $firstName.val(),
            email: $email.val(),
            phone_number: $phoneNumber.val(),
            address: $address.val(),
            city: $city.val(),
            zip_code: $zipCode.val(),
            timezone: $selectTimezone.val(),
            ...collectCustomFieldValues(),
        };

        data.appointment = {
            start_datetime:
                moment(App.Utils.UI.getDateTimePickerValue($selectDate)).format('YYYY-MM-DD') +
                ' ' +
                moment($('.selected-hour').data('value'), 'HH:mm').format('HH:mm') +
                ':00',
            end_datetime: calculateEndDatetime(),
            notes: $notes.val(),
            is_unavailability: false,
            id_users_provider: $selectProvider.val(),
            id_services: $selectService.val(),
        };

        data.manage_mode = Number(manageMode);

        if (manageMode) {
            data.appointment.id = vars('appointment_data').id;
            data.customer.id = vars('customer_data').id;
        }

        $('input[name="post_data"]').val(JSON.stringify(data));
    }

    /**
     * This method calculates the end datetime of the current appointment.
     *
     * End datetime is depending on the service and start datetime fields.
     *
     * @return {String} Returns the end datetime in string format.
     */
    function calculateEndDatetime() {
        // Find selected service duration.
        const serviceId = $selectService.val();

        const service = vars('available_services').find(
            (availableService) => Number(availableService.id) === Number(serviceId),
        );

        // Add the duration to the start datetime.
        const selectedDate = moment(App.Utils.UI.getDateTimePickerValue($selectDate)).format('YYYY-MM-DD');

        const selectedHour = $('.selected-hour').data('value'); // HH:mm

        const startMoment = moment(selectedDate + ' ' + selectedHour);

        let endMoment;

        if (service.duration && startMoment) {
            endMoment = startMoment.clone().add({'minutes': parseInt(service.duration)});
        } else {
            endMoment = moment();
        }

        return endMoment.format('YYYY-MM-DD HH:mm:ss');
    }

    /**
     * This method applies the appointment's data to the wizard so
     * that the user can start making changes on an existing record.
     *
     * @param {Object} appointment Selected appointment's data.
     * @param {Object} provider Selected provider's data.
     * @param {Object} customer Selected customer's data.
     *
     * @return {Boolean} Returns the operation result.
     */
    function applyAppointmentData(appointment, provider, customer) {
        try {
            // Select Service & Provider
            $selectService.val(appointment.id_services).trigger('change');
            $selectProvider.val(appointment.id_users_provider);

            // Set Appointment Date
            const startMoment = moment(appointment.start_datetime);
            App.Utils.UI.setDateTimePickerValue($selectDate, startMoment.toDate());
            App.Http.Booking.getAvailableHours(startMoment.format('YYYY-MM-DD'));

            // Update unavailable dates while in manage mode

            App.Http.Booking.getUnavailableDates(
                appointment.id_users_provider,
                appointment.id_services,
                startMoment.format('YYYY-MM-DD'),
            );

            // Apply Customer's Data
            $lastName.val(customer.last_name);
            $firstName.val(customer.first_name);
            $email.val(customer.email);
            $phoneNumber.val(customer.phone_number);
            $address.val(customer.address);
            $city.val(customer.city);
            $zipCode.val(customer.zip_code);
            if (customer.timezone) {
                $selectTimezone.val(customer.timezone);
            }
            const appointmentNotes = appointment.notes !== null ? appointment.notes : '';
            $notes.val(appointmentNotes);

            for (let i = 1; i <= getCustomFieldCount(); i++) {
                setCustomFieldValue(i, customer['custom_field_' + i]);
            }

            App.Pages.Booking.updateConfirmFrame();

            return true;
        } catch (exc) {
            return false;
        }
    }

    /**
     * Update the service description and information.
     *
     * This method updates the HTML content with a brief description of the
     * user selected service (only if available in db). This is useful for the
     * customers upon selecting the correct service.
     *
     * @param {Number} serviceId The selected service record id.
     */
    function updateServiceDescription(serviceId) {
        const $serviceDescription = $('#service-description');

        $serviceDescription.empty();

        const service = vars('available_services').find(
            (availableService) => Number(availableService.id) === Number(serviceId),
        );

        if (!service) {
            return; // Service not found
        }

        // Render the additional service information

        const additionalInfoParts = [];

        if (service.duration) {
            additionalInfoParts.push(`${lang('duration')}: ${service.duration} ${lang('minutes')}`);
        }

        if (Number(service.price) > 0) {
            additionalInfoParts.push(`${lang('price')}: ${Number(service.price).toFixed(2)} ${service.currency}`);
        }

        if (service.location) {
            additionalInfoParts.push(`${lang('location')}: ${service.location}`);
        }

        if (additionalInfoParts.length) {
            $(`
                <div class="mb-2 fst-italic">
                    ${additionalInfoParts.join(', ')}
                </div>
            `).appendTo($serviceDescription);
        }

        // Render the service description

        if (service.description?.length) {
            const escapedDescription = App.Utils.String.escapeHtml(service.description);

            const multiLineDescription = escapedDescription.replaceAll('\n', '<br/>');

            $(`
                <div class="text-muted">
                    ${multiLineDescription}
                </div>
            `).appendTo($serviceDescription);
        }
    }

    /**
     * Save customer information to localStorage.
     */
    function saveCustomerInfo() {
        const customerInfo = {
            firstName: $firstName.val(),
            lastName: $lastName.val(),
            email: $email.val(),
            phoneNumber: $phoneNumber.val(),
            address: $address.val(),
            city: $city.val(),
            zipCode: $zipCode.val(),
            rememberMe: true,
        };

        for (let i = 1; i <= getCustomFieldCount(); i++) {
            customerInfo['customField' + i] = getCustomFieldValue(i);
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(customerInfo));
        } catch (e) {
            console.warn('Could not save customer info to localStorage:', e);
        }
    }

    /**
     * Load customer information from localStorage.
     * GET parameters have priority over stored values.
     */
    function loadCustomerInfo() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);

            if (!stored) {
                return;
            }

            const customerInfo = JSON.parse(stored);

            // Restore remember me checkbox state
            if (customerInfo.rememberMe) {
                $rememberMe.prop('checked', true);
            }

            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Only populate fields that don't have GET params and are empty
            if (!urlParams.has('first_name') && !$firstName.val()) {
                $firstName.val(customerInfo.firstName || '');
            }
            if (!urlParams.has('last_name') && !$lastName.val()) {
                $lastName.val(customerInfo.lastName || '');
            }
            if (!urlParams.has('email') && !$email.val()) {
                $email.val(customerInfo.email || '');
            }
            if (!urlParams.has('phone') && !urlParams.has('phone_number') && !$phoneNumber.val()) {
                $phoneNumber.val(customerInfo.phoneNumber || '');
            }
            if (!urlParams.has('address') && !$address.val()) {
                $address.val(customerInfo.address || '');
            }
            if (!urlParams.has('city') && !$city.val()) {
                $city.val(customerInfo.city || '');
            }
            if (!urlParams.has('zip') && !urlParams.has('zip_code') && !$zipCode.val()) {
                $zipCode.val(customerInfo.zipCode || '');
            }

            for (let i = 1; i <= getCustomFieldCount(); i++) {
                const key = 'custom_field_' + i;

                if (!urlParams.has(key) && !getCustomFieldValue(i)) {
                    setCustomFieldValue(i, customerInfo['customField' + i] || '');
                }
            }
        } catch (e) {
            console.warn('Could not load customer info from localStorage:', e);
        }
    }

    /**
     * Clear customer information from localStorage.
     */
    function clearCustomerInfo() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            console.warn('Could not clear customer info from localStorage:', e);
        }
    }

    /**
     * Initialize the remember me functionality.
     */
    function initializeRememberMe() {
        // Skip if in manage mode (checkbox not present, use appointment data)
        if (manageMode || !$rememberMe.length) {
            return;
        }

        // Load stored customer info on page load
        loadCustomerInfo();

        // Handle remember me checkbox change
        $rememberMe.on('change', function () {
            if ($(this).prop('checked')) {
                saveCustomerInfo();
            } else {
                clearCustomerInfo();
            }
        });

        // Save customer info before form submission if remember me is checked
        $bookAppointmentSubmit.on('click', function () {
            if ($rememberMe.prop('checked')) {
                saveCustomerInfo();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        manageMode,
        updateConfirmFrame,
        updateServiceDescription,
        validateCustomerForm,
    };
})();
