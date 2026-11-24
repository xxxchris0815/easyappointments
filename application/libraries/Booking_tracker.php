<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Booking process tracking client.
 * ---------------------------------------------------------------------------- */

/**
 * Booking Tracker Library
 *
 * Forwards booking funnel events to a configured webhook.
 *
 * @package Libraries
 */
class Booking_tracker
{
    /**
     * Dispatch a booking tracking payload when the feature is enabled.
     *
     * @param array $payload Tracking payload.
     */
    public function track(array $payload): void
    {
        if (!filter_var(setting('booking_tracking_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $url = trim((string) setting('booking_tracking_webhook_url'));

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $body = array_merge(
            [
                'event' => $payload['event'] ?? 'booking_progress',
                'timestamp' => gmdate('c'),
            ],
            $payload,
        );

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
