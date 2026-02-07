#!/bin/bash

echo "--- 1. Скачиваю свежий Ioncube Loader ---"
wget -q https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
tar -xzf ioncube_loaders_lin_x86-64.tar.gz

echo "--- 2. Копирую нужную версию (8.2) ---"
cp ioncube/ioncube_loader_lin_8.2.so ./ioncube_loader.so

echo "--- 3. Создаю файл конфигурации ---"
mkdir -p php-conf
echo "zend_extension=/var/www/html/ioncube_loader.so" > php-conf/ioncube.ini

echo "--- 4. Обновляю docker-compose.yml ---"
cat <<EOT > docker-compose.yml
services:
  wisecp:
    image: thecodingmachine/php:8.2-v4-apache
    ports:
      - "8081:80"
    environment:
      - PHP_EXTENSION_MYSQLI=1
      - PHP_EXTENSION_PDO_MYSQL=1
      - PHP_EXTENSION_INTL=1
      - PHP_EXTENSION_GD=1
      - PHP_EXTENSION_BCMATH=1
      - PHP_EXTENSION_ZIP=1
      - PHP_INI_MEMORY_LIMIT=512M
      - PHP_INI_SESSION_SAVE_PATH=/tmp
    volumes:
      - .:/var/www/html
      # Пробрасываем конфиг прямо в Apache
      - ./php-conf/ioncube.ini:/etc/php/8.2/apache2/conf.d/00-ioncube.ini
      - ./php-conf/ioncube.ini:/etc/php/8.2/cli/conf.d/00-ioncube.ini
EOT

echo "--- 5. Перезапуск ---"
docker compose down && docker compose up -d

echo "--- 6. Проверка через 5 секунд ---"
sleep 5
docker exec wisecp-wisecp-1 php -v | grep ionCube
