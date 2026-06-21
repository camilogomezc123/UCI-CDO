#!/usr/bin/env bash

# Respaldo cifrado y verificable de la base clínica PostgreSQL de Tablero UCI.
set -Eeuo pipefail

APP_NAME="tablerouci"
APP_PATH="/var/www/tablerouci.koqoi.com/public"
BACKUP_ROOT="/var/backups/tablerouci"
PASSPHRASE_FILE="/root/.config/tablerouci-backup/passphrase"
REMOTE_ROOT="tablerouci-corporate-drive:Respaldos/Respaldos Tablero UCI/Datos clínicos"
TIMESTAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
WORK_DIR=""
DUMP_NAME="${APP_NAME}_${TIMESTAMP}.dump"
ENCRYPTED_NAME="${DUMP_NAME}.gpg"
LOG_FILE="/var/log/tablerouci_backup.log"

cleanup() {
    [[ -n "$WORK_DIR" ]] && rm -rf "$WORK_DIR"
}
trap cleanup EXIT
umask 077

[[ -s "$PASSPHRASE_FILE" ]] || { echo "No se encontró la clave de cifrado." >&2; exit 1; }

env_value() {
    sed -n "s/^$1=//p" "$APP_PATH/.env" | tail -n 1
}

DB_CONNECTION="$(env_value DB_CONNECTION)"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

[[ "$DB_CONNECTION" == 'pgsql' ]] || { echo "La aplicación no está configurada para PostgreSQL." >&2; exit 1; }
[[ -n "$DB_DATABASE" && -n "$DB_USERNAME" && -n "$DB_PASSWORD" ]] || { echo "Faltan credenciales PostgreSQL." >&2; exit 1; }

mkdir -p "$BACKUP_ROOT"
WORK_DIR="$(mktemp -d "${BACKUP_ROOT}/run.XXXXXX")"

exec 9>"/run/lock/tablerouci-clinical-backup.lock"
if ! flock -n 9; then
    echo "$(date --iso-8601=seconds) Respaldo omitido: ya existe otra ejecución." >> "$LOG_FILE"
    exit 0
fi

echo "$(date --iso-8601=seconds) Inicio del respaldo clínico." >> "$LOG_FILE"

# Formato custom: compacto, consistente y verificable mediante pg_restore.
PGPASSWORD="$DB_PASSWORD" pg_dump \
    -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" \
    --format=custom --no-owner --no-acl "$DB_DATABASE" > "$WORK_DIR/$DUMP_NAME"
pg_restore --list "$WORK_DIR/$DUMP_NAME" > /dev/null

gpg --batch --yes --pinentry-mode loopback --cipher-algo AES256 --compress-algo zlib \
    --passphrase-file "$PASSPHRASE_FILE" --symmetric \
    --output "$WORK_DIR/$ENCRYPTED_NAME" "$WORK_DIR/$DUMP_NAME"
rm -f "$WORK_DIR/$DUMP_NAME"
sha256sum "$WORK_DIR/$ENCRYPTED_NAME" > "$WORK_DIR/${ENCRYPTED_NAME}.sha256"

upload_tier() {
    local tier="$1"
    rclone copyto "$WORK_DIR/$ENCRYPTED_NAME" "$REMOTE_ROOT/$tier/$ENCRYPTED_NAME"
    rclone copyto "$WORK_DIR/${ENCRYPTED_NAME}.sha256" "$REMOTE_ROOT/$tier/${ENCRYPTED_NAME}.sha256"
}

# Crear los niveles también en días que no corresponden a copia semanal o
# mensual: así la limpieza de retención es idempotente desde la primera corrida.
rclone mkdir "$REMOTE_ROOT/diarios"
rclone mkdir "$REMOTE_ROOT/semanales"
rclone mkdir "$REMOTE_ROOT/mensuales"

upload_tier diarios
[[ "$(date '+%u')" == '7' ]] && upload_tier semanales
[[ "$(date '+%d')" == '01' ]] && upload_tier mensuales

LOCAL_SIZE="$(stat -c '%s' "$WORK_DIR/$ENCRYPTED_NAME")"
REMOTE_SIZE="$(rclone lsl "$REMOTE_ROOT/diarios/$ENCRYPTED_NAME" | awk '{print $1}')"
[[ "$LOCAL_SIZE" == "$REMOTE_SIZE" ]] || { echo 'El tamaño remoto no coincide.' >&2; exit 1; }

rclone copyto "$REMOTE_ROOT/diarios/$ENCRYPTED_NAME" "$WORK_DIR/remote-verify.gpg"
gpg --batch --yes --pinentry-mode loopback --passphrase-file "$PASSPHRASE_FILE" \
    --decrypt --output "$WORK_DIR/restore-test.dump" "$WORK_DIR/remote-verify.gpg"
pg_restore --list "$WORK_DIR/restore-test.dump" > /dev/null

rclone delete "$REMOTE_ROOT/diarios" --min-age 7d
rclone delete "$REMOTE_ROOT/semanales" --min-age 84d
rclone delete "$REMOTE_ROOT/mensuales" --min-age 365d

echo "$(date --iso-8601=seconds) Respaldo completado: $ENCRYPTED_NAME ($LOCAL_SIZE bytes)." >> "$LOG_FILE"
