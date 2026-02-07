@echo off
REM Название: cleanup-temp.bat
REM Описание: Удаление временных файлов пользователей (демо)
REM Автор: craftven dev
REM Дата: 2026-02-07
REM Лицензия: MIT

echo Cleaning %%TEMP%%
del /f /s /q "%%TEMP%%\*" 2>nul || echo Failed to delete some temp files

echo Done.
pause
