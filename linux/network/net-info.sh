#!/bin/bash
# Название: net-info.sh
# Описание: Быстрая диагностика сетевого состояния
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

echo "=== IP адреса ==="
ip -c addr show || ifconfig || true

echo "\n=== Маршруты ==="
ip route show || route -n || true

echo "\n=== Сетевые сервисы (состояние) ==="
ss -tunlp || netstat -tulpn || true

echo "\n=== DNS ==="
cat /etc/resolv.conf || true

echo "\n=== Проверка до 1.1.1.1 ==="
ping -c 3 1.1.1.1 || true
