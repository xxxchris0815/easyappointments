<?php
/* ----------------------------------------------------------------------------
 * Easy!Appointments - Docker production config example
 *
 * Copy this file to `config.php` next to docker-compose.prod.yml and replace
 * every <PLACEHOLDER> with your own values. Never commit config.php.
 * ---------------------------------------------------------------------------- */

class Config
{
    // Public URL of this installation (no trailing slash)
    // Examples: https://bookings.example.com  or  http://<SERVER_IP>:8080
    const BASE_URL = '<PUBLIC_BASE_URL>';

    const LANGUAGE = 'english';

    // Keep false in production
    const DEBUG_MODE = false;

    // Must match MYSQL_* values from `.env.prod`
    // Inside Docker Compose, the DB host is always the service name `mysql`
    const DB_HOST = 'mysql';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = '<DB_APP_USERNAME>';
    const DB_PASSWORD = '<DB_APP_PASSWORD>';

    // Optional Google Calendar sync (can also be set later in the admin UI)
    // const GOOGLE_SYNC_FEATURE = false;
    // const GOOGLE_CLIENT_ID = '<GOOGLE_CLIENT_ID>';
    // const GOOGLE_CLIENT_SECRET = '<GOOGLE_CLIENT_SECRET>';
}
