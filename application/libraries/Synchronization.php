<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

/**
 * Synchronization library.
 *
 * Handles external calendar synchronization functionality.
 *
 * @package Libraries
 */
class Synchronization
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Synchronization constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('providers_model');
        $this->CI->load->model('appointments_model');

        $this->CI->load->library('google_sync');
        $this->CI->load->library('caldav_sync');
    }

    /**
     * Synchronize changes made to the appointment with external calendars.
     *
     * @param array $appointment Appointment record.
     * @param array $service Service record.
     * @param array $provider Provider record.
     * @param array $customer Customer record.
     * @param array $settings Required settings for the notification content.
     */
    public function sync_appointment_saved(
        array $appointment,
        array $service,
        array $provider,
        array $customer,
        array $settings,
    ): void {
        try {
            // Google

            if ($provider['settings']['google_sync']) {
                if (empty($provider['settings']['google_token'])) {
                    throw new RuntimeException('No google token available for the provider: ' . $provider['id']);
                }

                $google_token = json_decode($provider['settings']['google_token'], true);

                $this->CI->google_sync->refresh_token($google_token['refresh_token']);

                if (empty($appointment['id_google_calendar'])) {
                    $google_event = $this->CI->google_sync->add_appointment(
                        $appointment,
                        $provider,
                        $service,
                        $customer,
                        $settings,
                    );

                    $appointment['id_google_calendar'] = $google_event->getId();

                    $this->CI->appointments_model->save($appointment);
                } else {
                    try {
                        $this->CI->google_sync->update_appointment(
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $settings,
                        );
                    } catch (Throwable $update_error) {
                        // Stale Google ID after provider change (or deleted remote event):
                        // recreate on this provider's calendar instead of leaving a hole.
                        log_message(
                            'error',
                            'Synchronization - Google update failed for appointment (' .
                                ($appointment['id'] ?? '-') .
                                '), recreating: ' .
                                $update_error->getMessage(),
                        );

                        $appointment['id_google_calendar'] = null;
                        $this->CI->db->update(
                            'appointments',
                            ['id_google_calendar' => null],
                            ['id' => (int) $appointment['id']],
                        );

                        $google_event = $this->CI->google_sync->add_appointment(
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $settings,
                        );

                        $appointment['id_google_calendar'] = $google_event->getId();
                        $this->CI->appointments_model->save($appointment);
                    }
                }
            }

            // CalDAV

            if ($provider['settings']['caldav_sync']) {
                $appointment['id_caldav_calendar'] = $this->CI->caldav_sync->save_appointment(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                );

                $this->CI->appointments_model->save($appointment);
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Synchronization - Could not sync confirmation details of appointment (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );

            log_message('error', $e->getTraceAsString());
        }
    }

    /**
     * Synchronize removal of an appointment with external calendars.
     *
     * @param array $appointment Appointment record.
     * @param array $provider Provider record.
     */
    public function sync_appointment_deleted(array $appointment, array $provider): void
    {
        try {
            // Google

            if ($provider['settings']['google_sync'] && !empty($appointment['id_google_calendar'])) {
                if (empty($provider['settings']['google_token'])) {
                    throw new RuntimeException('No google token available for the provider: ' . $provider['id']);
                }

                $google_token = json_decode($provider['settings']['google_token'], true);

                $this->CI->google_sync->refresh_token($google_token['refresh_token']);

                $this->CI->google_sync->delete_appointment($provider, $appointment['id_google_calendar']);
            }

            // CalDAV

            if ($provider['settings']['caldav_sync'] && !empty($appointment['id_caldav_calendar'])) {
                $this->CI->caldav_sync->delete_event($provider, $appointment['id_caldav_calendar']);
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Synchronization - Could not sync cancellation details of appointment (' .
                    ($appointment['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }

    /**
     * Synchronize changes made to the unavailability with external calendars.
     *
     * @param array $unavailability Unavailability record.
     * @param array $provider Provider record.
     */
    public function sync_unavailability_saved(array $unavailability, array $provider): void
    {
        try {
            // Google: do not push Unavailabilities.
            // Google→EA imports are Unavailabilities with id_google_calendar; pushing them
            // back would create duplicate Google events and sync loops.
            // Only Easy!Appointments bookings are written to Google Calendar.

            // CalDAV

            if ($provider['settings']['caldav_sync']) {
                $unavailability['id_caldav_calendar'] = $this->CI->caldav_sync->save_unavailability(
                    $unavailability,
                    $provider,
                );

                $this->CI->unavailabilities_model->save($unavailability);
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Synchronization - Could not sync cancellation details of unavailability (' .
                    ($unavailability['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }

    /**
     * Synchronize removal of an unavailability with external calendars.
     *
     * @param array $unavailability Unavailability record.
     * @param array $provider Provider record.
     */
    public function sync_unavailability_deleted(array $unavailability, array $provider): void
    {
        try {
            // Google: do not delete remote events for Unavailabilities.
            // Imported Google busy blocks must remain in Google when the local
            // Unavailability is removed or cleaned up by sync.

            // CalDAV

            if ($provider['settings']['caldav_sync'] && !empty($unavailability['id_caldav_calendar'])) {
                $this->CI->caldav_sync->delete_event($provider, $unavailability['id_caldav_calendar']);
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Synchronization - Could not sync cancellation details of unavailability (' .
                    ($unavailability['id'] ?? '-') .
                    ') : ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }

    /**
     * Remove a synced appointment from the previous provider's Google/CalDAV calendar
     * when the assignee changes, and clear local calendar IDs so the next sync creates
     * a fresh event on the new provider.
     *
     * @param array $appointment Appointment after reassignment (new id_users_provider).
     * @param array $previous_appointment Appointment row before the change.
     *
     * @return array Appointment with calendar IDs cleared when the provider changed.
     */
    public function move_appointment_on_provider_change(array $appointment, array $previous_appointment): array
    {
        $old_provider_id = (int) ($previous_appointment['id_users_provider'] ?? 0);
        $new_provider_id = (int) ($appointment['id_users_provider'] ?? 0);

        if ($old_provider_id <= 0 || $new_provider_id <= 0 || $old_provider_id === $new_provider_id) {
            return $appointment;
        }

        $remote_appointment = $previous_appointment;

        // Prefer calendar IDs already on the updated row if the previous snapshot lacked them.
        if (empty($remote_appointment['id_google_calendar']) && !empty($appointment['id_google_calendar'])) {
            $remote_appointment['id_google_calendar'] = $appointment['id_google_calendar'];
        }

        if (empty($remote_appointment['id_caldav_calendar']) && !empty($appointment['id_caldav_calendar'])) {
            $remote_appointment['id_caldav_calendar'] = $appointment['id_caldav_calendar'];
        }

        if (
            !empty($remote_appointment['id_google_calendar']) ||
            !empty($remote_appointment['id_caldav_calendar'])
        ) {
            try {
                $old_provider = $this->CI->providers_model->find($old_provider_id);
                $this->sync_appointment_deleted($remote_appointment, $old_provider);
            } catch (Throwable $e) {
                log_message(
                    'error',
                    'Synchronization - Could not remove appointment from previous provider (' .
                        $old_provider_id .
                        ') after reassignment: ' .
                        $e->getMessage(),
                );
            }
        }

        $appointment['id_google_calendar'] = null;
        $appointment['id_caldav_calendar'] = null;

        if (!empty($appointment['id'])) {
            $this->CI->db->update(
                'appointments',
                [
                    'id_google_calendar' => null,
                    'id_caldav_calendar' => null,
                ],
                ['id' => (int) $appointment['id']],
            );
        }

        return $appointment;
    }

    /**
     * Backward-compatible wrapper used by Calendar::save_appointment.
     *
     * Pass the new provider ID so the old calendar event can actually be removed.
     * Returns true when the provider changed.
     *
     * @param int $appointment_id Appointment ID.
     * @param int|null $new_provider_id Provider ID after the edit.
     */
    public function remove_appointment_on_provider_change($appointment_id, $new_provider_id = null): bool
    {
        if ($new_provider_id === null) {
            // Legacy callers compared the provider to itself — never deleted anything.
            return false;
        }

        $existing_appointment = $this->CI->appointments_model->find((int) $appointment_id);

        if ((int) $existing_appointment['id_users_provider'] === (int) $new_provider_id) {
            return false;
        }

        $appointment = $existing_appointment;
        $appointment['id_users_provider'] = (int) $new_provider_id;
        $this->move_appointment_on_provider_change($appointment, $existing_appointment);

        return true;
    }

    /**
     * Whether two appointment snapshots reference different providers.
     */
    public function provider_changed(?array $previous_appointment, array $appointment): bool
    {
        if (empty($previous_appointment)) {
            return false;
        }

        return (int) ($previous_appointment['id_users_provider'] ?? 0) !==
            (int) ($appointment['id_users_provider'] ?? 0);
    }
}
