/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Client helpers for provider working-plan checks in the calendar.
 */
App.Utils.ProviderSlot = (function () {
    const moment = window.moment;

    /**
     * Providers that offer the service and are visible to the current user.
     *
     * @param {Number|String} serviceId
     * @returns {Array}
     */
    function getProvidersForService(serviceId) {
        return (vars('available_providers') || []).filter((provider) => {
            if (vars('role_slug') === App.Layouts.Backend.DB_SLUG_PROVIDER && Number(provider.id) !== Number(vars('user_id'))) {
                return false;
            }

            if (
                vars('role_slug') === App.Layouts.Backend.DB_SLUG_SECRETARY &&
                (vars('secretary_providers') || []).indexOf(Number(provider.id)) === -1
            ) {
                return false;
            }

            return (provider.services || []).some((id) => Number(id) === Number(serviceId));
        });
    }

    /**
     * Resolve the working-plan day object for a provider/date (including exceptions).
     *
     * @param {Object} provider
     * @param {Date|moment.Moment|string} date
     * @returns {Object|null}
     */
    function resolveDayPlan(provider, date) {
        const day = moment(date);
        const weekdayName = App.Utils.Date.getWeekdayName(parseInt(day.format('d'), 10));
        const dateStr = day.format('YYYY-MM-DD');

        let workingPlan = {};
        let exceptions = {};

        try {
            workingPlan = JSON.parse(provider?.settings?.working_plan || vars('company_working_plan') || '{}');
        } catch (error) {
            workingPlan = {};
        }

        try {
            const rawExceptions = JSON.parse(provider?.settings?.working_plan_exceptions || '[]');

            if (Array.isArray(rawExceptions)) {
                rawExceptions.forEach((exception) => {
                    const startDate = moment(exception.startDate);
                    const endDate = moment(exception.endDate);

                    while (startDate.isSameOrBefore(endDate)) {
                        const key = startDate.format('YYYY-MM-DD');
                        exceptions[key] =
                            exception.startTime || exception.start
                                ? {
                                      start: exception.startTime || exception.start,
                                      end: exception.endTime || exception.end,
                                      breaks: exception.breaks || [],
                                  }
                                : null;
                        startDate.add(1, 'day');
                    }
                });
            } else if (rawExceptions && typeof rawExceptions === 'object') {
                exceptions = rawExceptions;
            }
        } catch (error) {
            exceptions = {};
        }

        if (Object.prototype.hasOwnProperty.call(exceptions, dateStr)) {
            return exceptions[dateStr] && exceptions[dateStr].start ? exceptions[dateStr] : null;
        }

        return workingPlan[weekdayName] || null;
    }

    /**
     * Whether [start, end) fits inside the provider working plan (breaks excluded).
     *
     * @param {Object} provider
     * @param {Date|moment.Moment|string} start
     * @param {Date|moment.Moment|string} end
     * @returns {Boolean}
     */
    function isWithinWorkingPlan(provider, start, end) {
        const startMoment = moment(start);
        const endMoment = moment(end);

        if (!startMoment.isValid() || !endMoment.isValid() || !endMoment.isAfter(startMoment)) {
            return false;
        }

        if (!startMoment.isSame(endMoment, 'day')) {
            return false;
        }

        const dayPlan = resolveDayPlan(provider, startMoment);

        if (!dayPlan?.start || !dayPlan?.end) {
            return false;
        }

        const dateStr = startMoment.format('YYYY-MM-DD');
        const workStart = moment(dateStr + ' ' + dayPlan.start, 'YYYY-MM-DD HH:mm');
        const workEnd = moment(dateStr + ' ' + dayPlan.end, 'YYYY-MM-DD HH:mm');

        if (startMoment.isBefore(workStart) || endMoment.isAfter(workEnd)) {
            return false;
        }

        const breaks = dayPlan.breaks || [];

        for (let i = 0; i < breaks.length; i++) {
            const breakStart = moment(dateStr + ' ' + breaks[i].start, 'YYYY-MM-DD HH:mm');
            const breakEnd = moment(dateStr + ' ' + breaks[i].end, 'YYYY-MM-DD HH:mm');

            if (startMoment.isBefore(breakEnd) && endMoment.isAfter(breakStart)) {
                return false;
            }
        }

        return true;
    }

    /**
     * First provider for the service that can take the slot by working plan.
     *
     * @param {Number|String} serviceId
     * @param {Date|moment.Moment|string} start
     * @param {Date|moment.Moment|string} end
     * @returns {Object|null}
     */
    function findProviderForSlot(serviceId, start, end) {
        const providers = getProvidersForService(serviceId);

        return (
            providers.find((provider) => isWithinWorkingPlan(provider, start, end)) ||
            null
        );
    }

    /**
     * Merge HH:mm ranges into a sorted union.
     *
     * @param {Array<{start:string,end:string}>} ranges
     * @returns {Array<{start:string,end:string}>}
     */
    function mergeTimeRanges(ranges) {
        const normalized = (ranges || [])
            .filter((range) => range?.start && range?.end && range.start < range.end)
            .map((range) => ({start: range.start, end: range.end}))
            .sort((a, b) => a.start.localeCompare(b.start));

        if (!normalized.length) {
            return [];
        }

        const merged = [normalized[0]];

        for (let i = 1; i < normalized.length; i++) {
            const current = normalized[i];
            const last = merged[merged.length - 1];

            if (current.start <= last.end) {
                if (current.end > last.end) {
                    last.end = current.end;
                }
            } else {
                merged.push({...current});
            }
        }

        return merged;
    }

    /**
     * Union of work windows for providers offering a service on a date.
     *
     * @param {Number|String} serviceId
     * @param {Date|moment.Moment|string} date
     * @returns {Array<{start:string,end:string}>}
     */
    function getServiceWorkWindows(serviceId, date) {
        const ranges = getProvidersForService(serviceId)
            .map((provider) => resolveDayPlan(provider, date))
            .filter((plan) => plan?.start && plan?.end)
            .map((plan) => ({start: plan.start, end: plan.end}));

        return mergeTimeRanges(ranges);
    }

    return {
        getProvidersForService,
        resolveDayPlan,
        isWithinWorkingPlan,
        findProviderForSlot,
        mergeTimeRanges,
        getServiceWorkWindows,
    };
})();
