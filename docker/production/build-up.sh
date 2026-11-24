#!/usr/bin/env bash
# Rebuild production stack with a visible build identity in the UI footer.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

export EA_GIT_COMMIT
export EA_GIT_BRANCH
export EA_BUILD_TIME
export EA_BUILD_ID

EA_GIT_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"
EA_GIT_BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"
EA_BUILD_TIME="$(date -u +%Y%m%d.%H%M)"
EA_BUILD_ID="${EA_BUILD_TIME}-${EA_GIT_COMMIT}"

echo "Building ${EA_BUILD_ID} (branch ${EA_GIT_BRANCH})"

docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build "$@"

echo "Done. Footer should show: build ${EA_BUILD_ID}"
