#!/bin/bash
# Название: ssh-hardening.sh
# Описание: Создает резервную копию /etc/ssh/sshd_config и выводит рекомендации по усилению
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

CONFIG=/etc/ssh/sshd_config
BACKUP_DIR=/var/backups/ssh-hardening

if [ "$EUID" -ne 0 ]; then
  echo "Требуются права root для выполнения резервного копирования и изменения конфигов. Запустите через sudo."
  exit 1
fi

mkdir -p "$BACKUP_DIR"
cp -av "$CONFIG" "$BACKUP_DIR/sshd_config.$(date +%F_%T)"

echo "Резервная копия $CONFIG сохранена в $BACKUP_DIR"

echo "Рекомендации (не применяются автоматически):"
echo " - Установите PermitRootLogin no"
echo " - Установите PasswordAuthentication no и используйте ключи SSH"
echo " - Используйте AllowUsers/AllowGroups для ограничения доступа"

echo "Если вы хотите автоматически применить рекомендации, запустите этот скрипт с флагом --apply"

if [ "${1-}" = "--apply" ]; then
  sed -i.bak -E 's/^#?PermitRootLogin.*/PermitRootLogin no/' "$CONFIG"
  sed -i.bak -E 's/^#?PasswordAuthentication.*/PasswordAuthentication no/' "$CONFIG"
  echo "Изменения применены (создан .bak файл). Перезапускаю sshd..."
  systemctl restart sshd || service ssh restart || true
  echo "Готово."
fi
