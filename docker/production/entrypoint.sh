#!/bin/sh
set -eu

SRC="${EA_SRC_DIR:-/usr/src/easyappointments}"
DST="${EA_WEB_DIR:-/var/www/html}"

mkdir -p "$DST"

# Sync baked application files into the shared web volume (keeps storage data).
# Anchor /config.php so only the root secrets file is skipped — never
# application/config/config.php (CodeIgniter requires that file).
rsync -a \
    --delete \
    --exclude '/storage/' \
    --exclude '/config.php' \
    "$SRC"/ "$DST"/

mkdir -p \
    "$DST/storage/backups" \
    "$DST/storage/cache" \
    "$DST/storage/logs" \
    "$DST/storage/sessions" \
    "$DST/storage/uploads"

# Preserve packaged storage placeholders if missing.
if [ -d "$SRC/storage" ]; then
    rsync -a --ignore-existing "$SRC/storage"/ "$DST/storage"/
fi

chown -R www-data:www-data "$DST/storage" || true
chmod -R ug+rwX "$DST/storage" || true

if [ ! -f "$DST/config.php" ]; then
    echo "ERROR: $DST/config.php is missing."
    echo "Copy config.docker.example.php to config.php, fill in your values, and mount it into the container."
    exit 1
fi

exec "$@"
