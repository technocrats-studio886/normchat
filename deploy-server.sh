#!/usr/bin/env bash
set -Eeuo pipefail

# Normchat safe deploy script (Docker Compose + Laravel)
# Usage:
#   ./deploy-server.sh [branch]
# Example:
#   ./deploy-server.sh main

BRANCH="${1:-neon}"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() {
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
  log "ERROR: $*"
  exit 1
}

on_error() {
  local line="$1"
  fail "Deploy berhenti di baris ${line}. Cek log di atas."
}
trap 'on_error ${LINENO}' ERR

cd "$PROJECT_DIR"

[[ -f docker-compose.yml ]] || fail "File docker-compose.yml tidak ditemukan di ${PROJECT_DIR}."
[[ -f .env ]] || fail "File .env tidak ditemukan. Buat dulu dari .env.docker.example dan isi secret production."

command -v git >/dev/null 2>&1 || fail "git tidak tersedia di server."
command -v docker >/dev/null 2>&1 || fail "docker tidak tersedia di server."

if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  fail "Docker Compose tidak ditemukan (docker compose / docker-compose)."
fi

# Load env vars from .env (for DB backup + checks)
set -a
# shellcheck disable=SC1091
source ./.env
set +a

APP_ENV_VALUE="${APP_ENV:-production}"
APP_DEBUG_VALUE="${APP_DEBUG:-false}"

if [[ "$APP_ENV_VALUE" == "production" && "$APP_DEBUG_VALUE" != "false" ]]; then
  fail "APP_ENV=production tapi APP_DEBUG bukan false. Set APP_DEBUG=false dulu demi keamanan."
fi

if [[ "${ALLOW_INSECURE_DEPLOY:-0}" != "1" && "$APP_ENV_VALUE" == "production" ]]; then
  if [[ "${DB_PASSWORD:-}" == "secret" || "${REVERB_APP_SECRET:-}" == "normchat-secret" || "${APP_KEY:-}" == "" ]]; then
    fail "Secret default/empty terdeteksi (.env). Ganti dulu (DB_PASSWORD, REVERB_APP_SECRET, APP_KEY) atau set ALLOW_INSECURE_DEPLOY=1 untuk bypass."
  fi
fi

log "Mulai deploy branch ${BRANCH} di ${PROJECT_DIR}"

log "Ambil update git terbaru"
git fetch --all --prune
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
  git checkout "$BRANCH"
fi
git pull --ff-only origin "$BRANCH"

log "Buat backup database sebelum update container"
BACKUP_DIR="${PROJECT_DIR}/storage/app/deploy-backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="${BACKUP_DIR}/db-$(date '+%Y%m%d-%H%M%S').sql"

if "${COMPOSE[@]}" ps postgres >/dev/null 2>&1; then
  "${COMPOSE[@]}" up -d postgres >/dev/null
  "${COMPOSE[@]}" exec -T postgres pg_dump -U "${DB_USERNAME:-normchat}" -d "${DB_DATABASE:-normchat}" > "$BACKUP_FILE"
  log "Backup database tersimpan: ${BACKUP_FILE}"
else
  log "Service postgres belum ada; skip backup."
fi

log "Build + jalankan semua service"
"${COMPOSE[@]}" up -d --build --remove-orphans postgres redis app queue reverb nginx

log "Jalankan migrasi + optimize cache Laravel"
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan config:cache
"${COMPOSE[@]}" exec -T app php artisan route:cache
"${COMPOSE[@]}" exec -T app php artisan view:cache
"${COMPOSE[@]}" exec -T app php artisan queue:restart || true

log "Health checks container"
"${COMPOSE[@]}" ps
"${COMPOSE[@]}" exec -T app php -r 'exit(extension_loaded("redis") ? 0 : 1);'

if command -v curl >/dev/null 2>&1; then
  APP_HEALTH_URL="${APP_URL:-http://127.0.0.1}/up"
  if curl -fsS --max-time 10 "$APP_HEALTH_URL" >/dev/null; then
    log "HTTP check OK: ${APP_HEALTH_URL}"
  else
    fail "HTTP check gagal: ${APP_HEALTH_URL}"
  fi
else
  log "curl tidak tersedia, skip HTTP check."
fi

log "Bersihkan dangling images (aman)"
docker image prune -f --filter dangling=true >/dev/null || true

log "Deploy selesai dengan aman."
