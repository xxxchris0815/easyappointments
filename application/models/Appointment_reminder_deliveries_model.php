<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Appointment reminder deliveries model.
 * ---------------------------------------------------------------------------- */

/**
 * Appointment_reminder_deliveries_model
 *
 * @package Models
 */
class Appointment_reminder_deliveries_model extends EA_Model
{
    /**
     * Replace reminder deliveries for an appointment.
     *
     * @param int $appointment_id
     * @param array $rows Delivery rows without id.
     */
    public function replace_pending_for_appointment(int $appointment_id, array $rows): void
    {
        // Remove previous schedule for this appointment so unique keys can be recreated after reschedule.
        $this->db->delete('appointment_reminder_deliveries', [
            'id_appointments' => $appointment_id,
        ]);

        foreach ($rows as $row) {
            $row['id_appointments'] = $appointment_id;
            $row['status'] = $row['status'] ?? 'pending';
            $row['create_datetime'] = date('Y-m-d H:i:s');
            $row['update_datetime'] = date('Y-m-d H:i:s');

            $this->db->insert('appointment_reminder_deliveries', $row);
        }
    }

    /**
     * Delete all deliveries for an appointment.
     */
    public function delete_for_appointment(int $appointment_id): void
    {
        $this->db->delete('appointment_reminder_deliveries', ['id_appointments' => $appointment_id]);
    }

    /**
     * Mark pending deliveries as skipped (e.g. after cancel).
     */
    public function skip_pending_for_appointment(int $appointment_id): void
    {
        $this->db->update(
            'appointment_reminder_deliveries',
            [
                'status' => 'skipped',
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            [
                'id_appointments' => $appointment_id,
                'status' => 'pending',
            ],
        );
    }

    /**
     * Fetch due pending deliveries joined with active appointments.
     *
     * @return array
     */
    public function get_due_pending(string $now): array
    {
        $appointments_table = $this->db->dbprefix('appointments');

        return $this->db
            ->select('appointment_reminder_deliveries.*, appointments.start_datetime, appointments.status AS appointment_status')
            ->from('appointment_reminder_deliveries')
            ->join('appointments', 'appointments.id = appointment_reminder_deliveries.id_appointments', 'inner')
            ->where('appointment_reminder_deliveries.status', 'pending')
            ->where('appointment_reminder_deliveries.due_datetime <=', $now)
            ->where('appointments.is_unavailability', false)
            ->where('appointments.start_datetime >', $now)
            ->where(
                'LOWER(COALESCE(`' . $appointments_table . "`.`status`, '')) NOT IN ('cancelled', 'canceled')",
                null,
                false,
            )
            ->order_by('appointment_reminder_deliveries.due_datetime', 'ASC')
            ->limit(100)
            ->get()
            ->result_array();
    }

    /**
     * Mark a delivery as sent.
     */
    public function mark_sent(int $delivery_id): void
    {
        $this->db->update(
            'appointment_reminder_deliveries',
            [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            ['id' => $delivery_id],
        );
    }

    /**
     * Mark a delivery as failed.
     */
    public function mark_failed(int $delivery_id, string $error): void
    {
        $this->db->update(
            'appointment_reminder_deliveries',
            [
                'status' => 'failed',
                'error_message' => substr($error, 0, 2000),
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            ['id' => $delivery_id],
        );
    }

    /**
     * Mark a delivery as skipped.
     */
    public function mark_skipped(int $delivery_id, string $reason = ''): void
    {
        $this->db->update(
            'appointment_reminder_deliveries',
            [
                'status' => 'skipped',
                'error_message' => $reason !== '' ? substr($reason, 0, 2000) : null,
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            ['id' => $delivery_id],
        );
    }
}
