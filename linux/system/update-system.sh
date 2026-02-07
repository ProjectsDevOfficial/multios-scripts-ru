#!/bin/bash
# Название: update-system.sh
# Описание: Обновление системы (apt/yum) с проверкой
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Запуск от root или через sudo требуется. Префикс sudo будет использован при необходимости."
  SUDO=sudo
else
  SUDO=
fi

if command -v apt >/dev/null 2>&1; then
  echo "Обнаружен apt — выполняю apt update && apt upgrade"
  $SUDO apt update && $SUDO apt -y upgrade
elif command -v dnf >/dev/null 2>&1; then
  echo "Обнаружен dnf — выполняю dnf upgrade"
  $SUDO dnf -y upgrade
elif command -v yum >/dev/null 2>&1; then
  echo "Обнаружен yum — выполняю yum update"
  $SUDO yum -y update
else
  echo "Неизвестный пакетный менеджер. Пожалуйста, обновите вручную."
  exit 1
fi

echo "Обновление завершено."
