#!/bin/bash

# 1. Определяем архитектуру и папку модулей
EXT_DIR=$(php -i | grep '^extension_dir' | awk '{print $NF}')
# В Codespaces Ubuntu 24.04 это обычно /usr/lib/php/20230831/

# 2. Установка (если вдруг не поставились)
sudo apt update && sudo apt install -y php8.3-mysql php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath

# 3. Находим IonCube
IONCUBE_PATH="/usr/lib/php/20230831/ioncube_loader_lin_8.3.so"
if [ ! -f "$IONCUBE_PATH" ]; then
    echo "--- IonCube не найден, качаю... ---"
    cd /tmp && wget -q https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
    tar -xzf ioncube_loaders_lin_x86-64.tar.gz
    sudo cp ioncube/ioncube_loader_lin_8.3.so /usr/lib/php/20230831/
fi

echo "--- Запуск с чистыми настройками ---"
cd /workspaces/vds-test/wisecp

# 4. ВАЖНО: Флаг -n отключает Xdebug и системный php.ini
# IonCube ДОЛЖЕН быть первым zend_extension
php -n \
  -d zend_extension="$IONCUBE_PATH" \
  -S 0.0.0.0:8081 -t . \
  -d memory_limit=512M \
  -d display_errors=1 \
  -d extension_dir="/usr/lib/php/20230831/" \
  -d extension=pdo_mysql.so \
  -d extension=mysqli.so \
  -d extension=mbstring.so \
  -d extension=intl.so \
  -d extension=gd.so \
  -d extension=bcmath.so \
  -d extension=zip.so