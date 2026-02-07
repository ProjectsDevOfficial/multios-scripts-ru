@echo off
REM Название: clear-dns.bat
REM Описание: Очистка кэша DNS и сброс сетевых настроек
REM Автор: craftven dev
REM Дата: 2026-02-07
REM Лицензия: MIT

REM Проверка прав администратора
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Ошибка: Этот скрипт требует прав администратора.
    echo Пожалуйста, запустите командную строку от администратора.
    pause
    exit /b 1
)

setlocal enabledelayedexpansion

echo.
echo ============================================
echo Очистка DNS кэша и сброс сетевых настроек
echo ============================================
echo.

echo Очищаю кэш DNS...
ipconfig /flushdns
echo [OK] Кэш очищен

echo.
echo Регистрирую DNS настройки...
ipconfig /registerdns
echo [OK] DNS зарегистрирован

echo.
echo Сбрасываю сетевые подключения...
ipconfig /release
ipconfig /renew
echo [OK] Сетевые подключения сброшены

echo.
echo Сбрасываю WinSock...
netsh winsock reset
echo [OK] WinSock сброшен

echo.
echo Сбрасываю IP настройки...
netsh int ip reset
echo [OK] IP сброшены

echo.
echo ============================================
echo Готово! Перезагрузитесь для применения.
echo ============================================
echo.
pause
