#!/bin/bash
# Название: provider-example.sh
# Описание: Демонстрационный скрипт обращения к API провайдера (пример)
# Автор: craftven dev
# Дата: 2026-02-07
# Лицензия: MIT

set -euo pipefail

API_URL="https://api.example-provider.local/v1/instances"
API_TOKEN="YOUR_API_TOKEN_HERE"

if [ -z "$API_TOKEN" ] || [ "$API_TOKEN" = "YOUR_API_TOKEN_HERE" ]; then
  echo "Установите переменную API_TOKEN в скрипте или через окружение перед запуском."
  exit 1
fi

curl -sS -H "Authorization: Bearer $API_TOKEN" "$API_URL" | jq '.' || true
