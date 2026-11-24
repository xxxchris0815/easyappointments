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
 * Business settings page.
 *
 * This module implements the functionality of the business settings page.
 */
App.Pages.BusinessSettings = (function () {
    const $saveSettings = $('#save-settings');
    const $applyGlobalWorkingPlan = $('#apply-global-working-plan');
    const $appointmentStatusOptions = $('#appointment-status-options');
    let workingPlanManager = null;

    /**
     * Check if the form has invalid values.
     *
     * @return {Boolean}
     */
    function isInvalid() {
        try {
            $('#business-settings .is-invalid').removeClass('is-invalid');

            // Validate required fields.

            let missingRequiredFields = false;

            $('#business-settings .required').each((index, requiredField) => {
                const $requiredField = $(requiredField);

                if (!$requiredField.val()) {
                    $requiredField.addClass('is-invalid');
                    missingRequiredFields = true;
                }
            });

            if (missingRequiredFields) {
                throw new Error(lang('fields_are_required'));
            }

            return false;
        } catch (error) {
            App.Layouts.Backend.displayNotification(error.message);
            return true;
        }
    }

    function deserialize(businessSettings) {
        businessSettings.forEach((businessSetting) => {
            const $field = $('[data-field="' + businessSetting.name + '"]');

            $field.is(':checkbox')
                ? $field.prop('checked', Boolean(Number(businessSetting.value)))
                : $field.val(businessSetting.value);
        });
    }

    function serialize() {
        const businessSettings = [];

        $('[data-field]').each((index, field) => {
            const $field = $(field);

            businessSettings.push({
                name: $field.data('field'),
                value: $field.is(':checkbox') ? Number($field.prop('checked')) : $field.val(),
            });
        });

        const workingPlan = workingPlanManager.get();

        if (workingPlan === null) {
            return null;
        }

        businessSettings.push({
            name: 'company_working_plan',
            value: JSON.stringify(workingPlan),
        });

        const appointmentStatusOptions = App.Components.AppointmentStatusOptions.getOptions($appointmentStatusOptions);

        businessSettings.push({
            name: 'appointment_status_options',
            value: JSON.stringify(appointmentStatusOptions),
        });

        const visibleFields = {};
        $('.calendar-modal-field-toggle').each((index, el) => {
            const $el = $(el);
            visibleFields[$el.data('calendar-modal-key')] = $el.prop('checked');
        });
        $('#calendar-modal-visible-fields').val(JSON.stringify(visibleFields));
        businessSettings.push({
            name: 'calendar_modal_visible_fields',
            value: JSON.stringify(visibleFields),
        });

        return businessSettings;
    }

    /**
     * Save the account information.
     */
    function onSaveSettingsClick() {
        if (isInvalid()) {
            App.Layouts.Backend.displayNotification(lang('settings_are_invalid'));

            return;
        }

        const businessSettings = serialize();

        if (businessSettings === null) {
            return;
        }

        App.Http.BusinessSettings.save(businessSettings).done(() => {
            App.Layouts.Backend.displayNotification(lang('settings_saved'));
        });
    }

    /**
     * Save the global working plan information.
     */
    function onApplyGlobalWorkingPlan() {
        const buttons = [
            {
                text: lang('cancel'),
                click: (event, messageModal) => {
                    messageModal.hide();
                },
            },
            {
                text: 'OK',
                click: (event, messageModal) => {
                    const workingPlan = workingPlanManager.get();

                    if (workingPlan === null) {
                        messageModal.hide();
                        return;
                    }

                    App.Http.BusinessSettings.applyGlobalWorkingPlan(workingPlan)
                        .done(() => {
                            App.Layouts.Backend.displayNotification(lang('working_plans_got_updated'));
                        })
                        .always(() => {
                            messageModal.hide();
                        });
                },
            },
        ];

        App.Utils.Message.show(lang('working_plan'), lang('overwrite_existing_working_plans'), buttons);
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        const businessSettings = vars('business_settings');

        deserialize(businessSettings);

        let companyWorkingPlan = {};
        let appointmentStatusOptions = [];
        let calendarModalVisibleFields = {};

        vars('business_settings').forEach((businessSetting) => {
            if (businessSetting.name === 'company_working_plan') {
                companyWorkingPlan = JSON.parse(businessSetting.value);
            }

            if (businessSetting.name === 'appointment_status_options') {
                appointmentStatusOptions = JSON.parse(businessSetting.value);
            }

            if (businessSetting.name === 'calendar_modal_visible_fields') {
                try {
                    calendarModalVisibleFields = JSON.parse(businessSetting.value || '{}') || {};
                } catch (error) {
                    calendarModalVisibleFields = {};
                }
            }
        });

        $('.calendar-modal-field-toggle').each((index, el) => {
            const $el = $(el);
            const key = $el.data('calendar-modal-key');
            const visible = calendarModalVisibleFields[key];
            $el.prop('checked', visible === undefined ? key !== 'utm' : Boolean(visible));
        });

        workingPlanManager = new App.Utils.WorkingPlan();
        workingPlanManager.setup(companyWorkingPlan);
        workingPlanManager.timepickers(false);
        workingPlanManager.addEventListeners();

        App.Components.AppointmentStatusOptions.setOptions($appointmentStatusOptions, appointmentStatusOptions);

        $saveSettings.on('click', onSaveSettingsClick);

        $applyGlobalWorkingPlan.on('click', onApplyGlobalWorkingPlan);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {};
})();
