#!/bin/bash

# Цвета для красоты
green='\033[0;32m'
blue='\033[0;34m'
clear='\033[0m'

echo -e "${blue}=== Выбери панель для установки на твой Xeon ===${clear}"
echo "1) CloudPanel (Быстрая, легкая, для PHP/JS)"
echo "2) FOSSBilling (Тот самый биллинг, бесплатный)"
echo "3) Cockpit (Официальная админка RedHat/Ubuntu)"
echo "4) HestiaCP (Классика, замена старой VestaCP)"
echo "5) FastPanel (Удобная, много функций, бесплатная)"
echo "6) Выход"

read -p "Введи номер (1-6): " choice

case $choice in
    1)
        echo -e "${green}Ставим CloudPanel...${clear}"
        wget https://repo.cloudpanel.io/install.sh && sudo bash install.sh
        ;;
    2)
        echo -e "${green}Ставим FOSSBilling (через Docker-compose)...${clear}"
        apt install docker-compose -y
        mkdir billing && cd billing
        wget https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/main/docker-compose.yml
        docker-compose up -d
        ;;
    3)
        echo -e "${green}Ставим Cockpit...${clear}"
        apt update && apt install cockpit -y
        ;;
    4)
        echo -e "${green}Ставим HestiaCP...${clear}"
        wget https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh
        bash hst-install.sh --force
        ;;
    5)
        echo -e "${green}Ставим FastPanel...${clear}"
        wget https://repo.fastpanel.direct/install_fastpanel.sh -O - | bash -
        ;;
    6)
        exit 0
        ;;
    *)
        echo "Неверный выбор"
        ;;
esac
