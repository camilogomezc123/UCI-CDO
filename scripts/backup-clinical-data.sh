#!/usr/bin/env bash

# Respaldo cifrado y verificable de la base clínica SQLite de Tablero UCI.
set -Eeuo pipefail

APP_NAME="tablerouci"
DATABASE_FILE="/var/www/tablerouci.koqoi.com/public/database/database.sqlite"
BACKUP_ROOT="/var/backups/tablerouci"
PASSPHRASE_FILE="/root/.config/tablerouci-backup/passphrase"
REMOTE_ROOT="tablerouci-corporate-drive:Respaldos/Respaldos Tablero UCI/Datos clínicos"
TIMESTAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
WORK_DIR=""
DUMP_NAME="${APP_NAME}_${TIMESTAMP}.sqlite"
ENCRYPTED_NAME="${DUMP_NAME}.gpg"
LOG_FILE="/var/log/tablerouci_backup.log"

cleanup() {
    [[ -n "$WORK_DIR" ]] && rm -rf "$WORK_DIR"
}
trap cleanup EXIT
umask 077

[[ -s "$DATABASE_FILE" ]] || { echo "No se encontró la base SQLite." >&2; exit 1; }
[[ -s "$PASSPHRASE_FILE" ]] || { echo "No se encontró la clave de cifrado." >&2; exit 1; }

mkdir -p "$BACKUP_ROOT"
WORK_DIR="$(mktemp -d "${BACKUP_ROOT}/run.XXXXXX")"

exec 9>"/run/lock/tablerouci-clinical-backup.lock"
if ! flock -n 9; then
    echo "$(date --iso-8601=seconds) Respaldo omitido: ya existe otra ejecución." >> "$LOG_FILE"
    exit 0
fi

echo "$(date --iso-8601=seconds) Inicio del respaldo clínico." >> "$LOG_FILE"

# .backup crea una copia consistente aun si SQLite recibe una escritura.
sqlite3 "$DATABASE_FILE" ".backup '$WORK_DIR/$DUMP_NAME'"
sqlite3 "$WORK_DIR/$DUMP_NAME" 'PRAGMA integrity_check;' | grep -qx 'ok'

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

upload_tier diarios
[[ "$(date '+%u')" == '7' ]] && upload_tier semanales
[[ "$(date '+%d')" == '01' ]] && upload_tier mensuales

LOCAL_SIZE="$(stat -c '%s' "$WORK_DIR/$ENCRYPTED_NAME")"
REMOTE_SIZE="$(rclone lsl "$REMOTE_ROOT/diarios/$ENCRYPTED_NAME" | awk '{print $1}')"
[[ "$LOCAL_SIZE" == "$REMOTE_SIZE" ]] || { echo 'El tamaño remoto no coincide.' >&2; exit 1; }

rclone copyto "$REMOTE_ROOT/diarios/$ENCRYPTED_NAME" "$WORK_DIR/remote-verify.gpg"
gpg --batch --yes --pinentry-mode loopback --passphrase-file "$PASSPHRASE_FILE" \
    --decrypt --output "$WORK_DIR/restore-test.sqlite" "$WORK_DIR/remote-verify.gpg"
sqlite3 "$WORK_DIR/restore-test.sqlite" 'PRAGMA integrity_check;' | grep -qx 'ok'

rclone delete "$REMOTE_ROOT/diarios" --min-age 7d
rclone delete "$REMOTE_ROOT/semanales" --min-age 84d
rclone delete "$REMOTE_ROOT/mensuales" --min-age 365d

echo "$(date --iso-8601=seconds) Respaldo completado: $ENCRYPTED_NAME ($LOCAL_SIZE bytes)." >> "$LOG_FILE"
