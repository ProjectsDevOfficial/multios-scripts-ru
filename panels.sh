#!/bin/bash

# Цвета
green='\033[0;32m'
blue='\033[0;34m'
yellow='\033[1;33m'
clear='\033[0m'

echo -e "${blue}=== Выбери панель для установки на твой 4-Core Xeon ===${clear}"
echo "1)  CloudPanel (Быстрая, легкая, для PHP/JS)"
echo "2)  FOSSBilling (Бесплатный биллинг, Docker-версия)"
echo "3)  Cockpit (Стандартная админка Ubuntu/Debian)"
echo "4)  HestiaCP (Классика с тёмной темой)"
echo "5)  FastPanel (Удобная и многофункциональная)"
echo "6)  aaPanel (Очень популярная, много плагинов)"
echo "7)  CyberPanel (На OpenLiteSpeed, очень быстрая)"
echo "8)  KeyHelp (Немецкое качество, очень стабильная)"
echo "9)  PestaShop (Если нужен сразу магазин)"
echo "10) WISECP (Тот самый биллинг, ставим через Docker)"
echo "11) WordPress (Просто чистый WP через Docker)"
echo "12) Выход"

read -p "Введи номер (1-12): " choice

case $choice in
    1)
        echo -e "${green}Ставим CloudPanel...${clear}"
        curl -sSL https://repo.cloudpanel.io/install.sh | sudo bash
        ;;
    2)
        echo -e "${green}Ставим FOSSBilling...${clear}"
        mkdir -p billing && cd billing
        curl -o docker-compose.yml https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/main/docker-compose.yml
        docker compose up -d
        ;;
    3)
        echo -e "${green}Ставим Cockpit...${clear}"
        sudo apt update && sudo apt install -y cockpit
        sudo service cockpit start
        ;;
    4)
        echo -e "${green}Ставим HestiaCP...${clear}"
        wget https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh
        sudo bash hst-install.sh --force --apache no --postgresql no --clamav no --spamassassin no
        ;;
    5)
        echo -e "${green}Ставим FastPanel...${clear}"
        wget -O - https://repo.fastpanel.direct/install_fastpanel.sh | sudo bash -
        ;;
    6)
        echo -e "${green}Ставим aaPanel...${clear}"
        URL=https://www.aapanel.com/script/install_6.0_en.sh && wget -O install.sh $URL && sudo bash install.sh forum
        ;;
    7)
        echo -e "${green}Ставим CyberPanel...${clear}"
        sh <(curl https://cyberpanel.net/install.sh || wget -O - https://cyberpanel.net/install.sh)
        ;;
    8)
        echo -e "${green}Ставим KeyHelp...${clear}"
        wget https://static.keyhelp.de/install.php -O install.php && sudo php install.php
        ;;
    9)
        echo -e "${green}Ставим PrestaShop (Docker)...${clear}"
        docker run -ti --name prestashop -p 8080:80 -d prestashop/prestashop
        ;;
    10)
        echo -e "${green}Ставим WISECP (Docker)...${clear}"
        # Тут используем твой прошлый конфиг или упрощенный запуск
        docker run -d -p 80:80 --name wisecp-v15 php:8.3-apache
        echo "Дальше нужно прокинуть IonCube, как мы делали раньше."
        ;;
    11)
        echo -e "${green}Ставим WordPress...${clear}"
        docker run -e WORDPRESS_DB_PASSWORD=password -p 8080:80 -d wordpress
        ;;
    12)
        exit 0
        ;;
    *)
        echo -e "${yellow}Неверный выбор, попробуй еще раз.${clear}"
        ;;
esac