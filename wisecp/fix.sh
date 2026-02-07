#!/bin/bash

echo "--- 1. Сброс прав ---"
sudo chown -R $(whoami):$(whoami) .
sudo chmod -R 777 .

echo "--- 2. Упрощенный конфиг (без IonCube вручную) ---"
mkdir -p php-conf
cat <<EOT > php-conf/custom.ini
memory_limit=512M
session.save_handler=files
session.save_path="/tmp"
display_errors=On
EOT

echo "--- 3. Обновление docker-compose.yml ---"
cat <<EOT > docker-compose.yml
services:
  wisecp:
    image: thecodingmachine/php:8.2-v4-apache
    ports:
      - "8081:80"
    environment:
      - PHP_EXTENSION_IONCUBE=1
      - PHP_EXTENSION_MYSQLI=1
      - PHP_EXTENSION_PDO_MYSQL=1
      - PHP_EXTENSION_INTL=1
      - PHP_EXTENSION_GD=1
      - PHP_EXTENSION_BCMATH=1
      - PHP_EXTENSION_ZIP=1
      - APACHE_DOCUMENT_ROOT=/var/www/html
      - PHP_INI_SESSION_SAVE_PATH=/tmp
    volumes:
      - .:/var/www/html
      - ./php-conf/custom.ini:/etc/php/8.2/apache2/conf.d/99-custom.ini
EOT

echo "--- 4. Перезапуск ---"
docker compose down
docker compose up -d

echo "--- 5. Ожидание и проверка логов ---"
sleep 10
docker logs wisecp-wisecp-1 | tail -n 10
