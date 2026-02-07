 # VPS/VDS скрипты
 
 Содержит утилиты для управления VPS/VDS: резервное копирование, мониторинг и примеры работы с провайдерами.
 
 Основные папки и примеры:
 
 - `vds-vps/providers/` — скрипты для конкретных провайдеров (provider-example.sh)
 - `vds-vps/monitoring/` — мониторинг ресурсов (monitor-cpu-mem.sh)
 - `vds-vps/backup/` — резервное копирование и ротация (backup-files.sh)
 
 Примеры запуска:
 
 ```bash
 ./vds-vps/monitoring/monitor-cpu-mem.sh
 sudo ./vds-vps/backup/backup-files.sh /путь/для/резервной/копии
 ```
 
 Смотрите README в подпапках для дополнительных инструкций.