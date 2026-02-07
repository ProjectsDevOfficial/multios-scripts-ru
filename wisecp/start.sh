#!/bin/bash

# 1. Устанавливаем системный PHP и ВСЕ нужные модули
# Это поставит ИМЕННО системные версии, которые дружат между собой
sudo apt update && sudo apt install -y php8.3-cli php8.3-mysql php8.3-intl php8.3-gd php8.3-bcmath php8.3-zip php8.3-mbstring php8.3-xml

# 2. Создаем конфиг ИМЕННО для системного PHP
cat <<EOF > wisecp.ini
; Подключаем IonCube ПЕРВЫМ
zend_extension="/usr/lib/php/20230831/ioncube_loader_lin_8.3.so"

memory_limit=512M
display_errors=On

; В системном PHP 8.3 расширения обычно подгружаются сами, 
; но мы укажем их явно на всякий случай через ПРАВИЛЬНЫЙ путь
extension=pdo_mysql.so
extension=mysqli.so
extension=mbstring.so
extension=intl.so
extension=gd.so
extension=bcmath.so
extension=zip.so
EOF

echo "--- ЗАПУСК ЧЕРЕЗ СИСТЕМНЫЙ PHP (/usr/bin/php8.3) ---"

# 3. Запуск, ИГНОРИРУЯ кастомную папку
/usr/bin/php8.3 -c wisecp.ini -S 0.0.0.0:8081 -t .