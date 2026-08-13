# Docker Production Deployment

Step-by-step guide to deploy **this Easy!Appointments fork** with Docker Compose.

This stack is meant for real hosting (or a VPS staging box). For local development with Mailpit/LDAP/Baikal, use the default `docker-compose.yml` instead (see [docker.md](docker.md)).

## What you get

| Service | Role |
|---------|------|
| `nginx` | HTTP frontend |
| `app` | PHP-FPM application |
| `mysql` | Database |
| `reminders` | Background worker for appointment reminders |

Default URL after deploy: `http://<SERVER_IP_OR_HOST>:8080`

## Prerequisites

- A Linux server (or WSL) with Docker Engine + Docker Compose plugin
- Git
- An open TCP port for the app (default `8080`, or put a reverse proxy in front)
- DNS/hostname if you will use HTTPS later

## 1. Get the code

```bash
git clone <YOUR_FORK_GIT_URL>
cd easyappointments
git checkout <YOUR_BRANCH_OR_TAG>
```

## 2. Create environment and config files

```bash
cp .env.prod.example .env.prod
cp config.docker.example.php config.php
```

Edit `.env.prod` and replace every `<PLACEHOLDER>`:

```env
APP_PORT=8080
TZ=UTC
REMINDER_INTERVAL_SECONDS=60

MYSQL_DATABASE=easyappointments
MYSQL_USER=<DB_APP_USERNAME>
MYSQL_PASSWORD=<DB_APP_PASSWORD>
MYSQL_ROOT_PASSWORD=<DB_ROOT_PASSWORD>
```

Edit `config.php` and replace every `<PLACEHOLDER>`:

```php
const BASE_URL      = '<PUBLIC_BASE_URL>';  // e.g. http://bookings.example.com:8080
const DB_HOST       = 'mysql';
const DB_NAME       = 'easyappointments';
const DB_USERNAME   = '<DB_APP_USERNAME>';  // same as MYSQL_USER
const DB_PASSWORD   = '<DB_APP_PASSWORD>';  // same as MYSQL_PASSWORD
const DEBUG_MODE    = false;
```

Notes:

- `DB_HOST` must stay `mysql` inside this Compose network.
- `BASE_URL` must match how users reach the site (scheme + host + port, **no trailing slash**).
- Do **not** commit `.env.prod` or `config.php`.

## 3. Build and start

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

First build can take several minutes (Composer + frontend assets).

Check status:

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml ps
docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f app nginx mysql reminders
```

## 4. Finish setup in the browser

1. Open `http://<SERVER_IP_OR_HOST>:8080` (or your chosen `APP_PORT` / domain).
2. Complete the Easy!Appointments installation wizard.
3. Create the initial admin account when prompted.
4. Log in and configure:
   - company / booking settings
   - services and providers
   - email (SMTP) under settings if you need outbound mail
   - optional integrations (Zoom, Mautic, Google Calendar, webhooks)
   - appointment reminder rules under booking settings

Fork migrations (ownership, soft-cancel, reminders, etc.) are applied through the normal EA install/upgrade path.

## 5. Verify reminders worker

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml logs --tail=50 reminders
```

You should see the worker loop running. Reminder emails/webhooks only fire when:

- reminders are enabled in Booking Settings
- at least one rule exists
- an appointment is due for a reminder
- SMTP/webhooks are configured as needed

## 6. (Recommended) Put HTTPS in front

Keep this Compose stack on an internal/localhost port and terminate TLS with a reverse proxy, for example:

- Caddy / Traefik / nginx on the host
- Cloudflare Tunnel / load balancer

Point the proxy to `127.0.0.1:8080` (or your `APP_PORT`), then set:

```php
const BASE_URL = 'https://bookings.example.com';
```

Rebuild/restart is not required for `config.php` changes (it is mounted), but clear browser caches after changing `BASE_URL`.

## Day-2 operations

### Stop / start

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml stop
docker compose --env-file .env.prod -f docker-compose.prod.yml start
```

### Update to a newer fork revision

```bash
git pull
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
```

### Database backup

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec mysql \
  mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" > backup-$(date +%F).sql
```

Prefer supplying credentials interactively or from your local env so they do not land in shell history.

### Application file backup

Persist at least:

- Docker volumes `ea_mysql` and `ea_storage`
- your host files `config.php` and `.env.prod`

### Shell into the app container

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec app bash
```

Manual reminder run:

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml exec app \
  php index.php console reminders
```

## Troubleshooting

| Symptom | What to check |
|---------|----------------|
| Container exits: missing `config.php` | Create it from `config.docker.example.php` in the project root |
| DB connection errors | `DB_*` in `config.php` must match `.env.prod` `MYSQL_*`; host must be `mysql` |
| Blank page / assets missing | Rebuild image: `up -d --build`; check `app` logs for gulp/composer errors |
| Wrong links / redirects | `BASE_URL` does not match the public URL |
| Reminders never send | Enable rules in UI; check `reminders` logs; configure SMTP/webhooks |
| Port already in use | Change `APP_PORT` in `.env.prod` |

## Security checklist

- Replace every `<PLACEHOLDER>` with unique strong secrets
- Keep `DEBUG_MODE = false`
- Do not publish MySQL ports publicly (this compose file does not)
- Restrict admin access and rotate API tokens / integration secrets
- Back up DB + `storage` regularly
- Prefer HTTPS via reverse proxy before going live

*This document applies to the production Compose files in this repository (`docker-compose.prod.yml`, `docker/production/*`).*
