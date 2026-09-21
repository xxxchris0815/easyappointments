# Google Calendar Sync

Easy!Appointments keeps provider calendars aligned in **both directions**, without creating loops:

1. **EA → Google:** Bookings created in Easy!Appointments are written to the provider’s Google Calendar.
2. **Google → EA:** Other (personal/busy) Google events are imported as **Unavailabilities** (blocked time) in Easy!Appointments.

Imported Unavailabilities are **never pushed back** to Google. That is the main loop/duplicate protection.

## ID linking

| Direction | What is stored |
|-----------|----------------|
| EA → Google | Google event gets private extended properties `ea_appointment_id` + `ea_origin=easyappointments`. EA stores the Google event ID in `appointments.id_google_calendar`. |
| Google → EA | EA Unavailability stores the Google event ID in `id_google_calendar`. |

Matching prefers these IDs. Time/title matching is only a fallback for older Google events that do not yet have EA metadata.

## What You Need

- A working Easy!Appointments installation with at least one service and provider set up.
- A Google account.

## Step 1: Create Google API Credentials

You need to tell Google that your Easy!Appointments installation is allowed to access calendar data.

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and **create a new project** (or select an existing one).
2. In the project dashboard, go to **APIs & Services** > **Library** and search for **Google Calendar API**. Click on it and press **Enable**.
3. Go to **APIs & Services** > **Credentials** and click **Create Credentials** > **OAuth client ID**.
4. If prompted, fill in the **OAuth consent screen** information first.
5. Select **Web Application** as the application type and give it a name.
6. Under **Authorized JavaScript origins**, add your domain (just the domain, e.g. `http://mywebsite.com`).
7. Under **Authorized redirect URIs**, add:
   ```
   https://your-domain.com/easyappointments/index.php/google/oauth_callback
   ```
   Replace `your-domain.com/easyappointments` with your actual installation URL.
8. Click **Create**. Google will show you a **Client ID** and **Client Secret** — copy both.

## Step 2: Configure Credentials in Easy!Appointments

You can now configure the Google **Client ID** and **Client Secret** directly from the Easy!Appointments user interface (Backend **Settings** → **Google Calendar** section).

As an alternative, you can still define them in `config.php`:

```php
const GOOGLE_SYNC_FEATURE   = TRUE;
const GOOGLE_CLIENT_ID      = 'your-client-id-here';
const GOOGLE_CLIENT_SECRET  = 'your-client-secret-here';
```

## Step 3: Link a Provider's Google Calendar

1. Log in to the Easy!Appointments backend and go to the **Calendar** page.
2. Select a provider and click **Enable Sync**.
3. A Google sign-in window will appear. Log in with the provider's Google account and grant permission.
4. Click **Synchronize** to run a full sync for the configured past/future day window.

## Good to Know

- Bookings: EA is source of truth → changes are pushed to Google.
- Foreign Google events: Google is source of truth → imported/updated as Unavailabilities; removed in Google ⇒ removed in EA.
- Events overlapping an existing EA booking are not imported again as busy blocks.
- Each provider can only be linked to **one** Google Calendar account.
- `BASE_URL` in `config.php` must be the public HTTPS domain (important behind reverse proxies).

## Cleanup: Reset Google Unavailabilities

If Easy!Appointments shows duplicate “Unavailable” / “Nichtverfügbarkeit” blocks (often leftover from older sync runs), open **Settings → Integrations → Google Calendar → Sync status & logs**.

For the affected provider, click **Reset & re-sync**. That will:

1. Delete only **Google-imported** unavailabilities in Easy!Appointments (`id_google_calendar` set).
2. Leave bookings and **manual** unavailabilities untouched.
3. Re-run Google sync so busy blocks are imported cleanly again.

Google Calendar events themselves are **not** deleted.

## Useful Links

- [Google Calendar API Docs](https://developers.google.com/google-apps/calendar)
- [E!A Support Group](https://groups.google.com/forum/#!forum/easy-appointments)

*This document applies to Easy!Appointments v1.6.0 (custom fork).*

[Back](readme.md)
