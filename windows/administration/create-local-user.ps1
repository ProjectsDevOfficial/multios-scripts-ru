<#
Название: create-local-user.ps1
Описание: Создание локального пользователя (Windows) — требует запуска от администратора
Автор: craftven dev
Дата: 2026-02-07
Лицензия: MIT
#>

param(
  [Parameter(Mandatory=$true)]
  [string]$Username,
  [Parameter(Mandatory=$true)]
  [string]$Password
)

if (-not ([bool](Test-Path "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion"))) {
  Write-Error "Скрипт должен быть выполнен на Windows"
  exit 1
}

if (Get-LocalUser -Name $Username -ErrorAction SilentlyContinue) {
  Write-Error "Пользователь $Username уже существует"
  exit 1
}

$securePass = ConvertTo-SecureString $Password -AsPlainText -Force
New-LocalUser -Name $Username -Password $securePass -FullName $Username -Description "Created by script"
Add-LocalGroupMember -Group "Users" -Member $Username
Write-Output "Пользователь $Username создан"
