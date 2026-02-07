#!/bin/bash
# Название: backup-files.sh
# Описание: Примитивный резервный скрипт: архивирует указанную директорию и держит ротацию
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

SRC_DIR="${1:-/var/www}"
DEST_DIR="/var/backups/vds-backups"
mkdir -p "$DEST_DIR"

TIMESTAMP=$(date +%F_%H%M%S)
ARCHIVE="$DEST_DIR/backup-$(basename "$SRC_DIR")-$TIMESTAMP.tar.gz"

echo "Архивация $SRC_DIR -> $ARCHIVE"
tar -czf "$ARCHIVE" -C "$(dirname "$SRC_DIR")" "$(basename "$SRC_DIR")"

echo "Ротация: сохраняем последние 7 архивов"
ls -1t "$DEST_DIR"/backup-*.tar.gz | tail -n +8 | xargs -r rm -f

echo "Резервное копирование завершено"
