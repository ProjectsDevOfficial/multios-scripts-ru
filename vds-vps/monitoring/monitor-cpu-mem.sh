#!/bin/bash
# Название: monitor-cpu-mem.sh
# Описание: Простая проверка загрузки CPU и памяти и вывод топ-процессов
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

INTERVAL=5
COUNT=12

echo "Мониторинг (интервал $INTERVAL сек, $COUNT итераций)"
for i in $(seq 1 $COUNT); do
  echo "\n=== $(date) ==="
  echo "-- CPU --"
  top -b -n1 | head -n5
  echo "-- MEMORY TOP 10 --"
  ps aux --sort=-%mem | head -n 12
  sleep $INTERVAL
done
