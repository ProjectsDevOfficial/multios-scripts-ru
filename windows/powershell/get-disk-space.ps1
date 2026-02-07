<#
Название: get-disk-space.ps1
Описание: Сбор информации о дисковом пространстве и экспорт в CSV
Автор: craftven dev
Дата: 2026-02-07
Лицензия: MIT
#>

Get-PSDrive -PSProvider FileSystem | Select-Object Name, @{Name='FreeGB';Expression={[math]::Round($_.Free/1GB,2)}}, @{Name='UsedGB';Expression={[math]::Round(($_.Used)/1GB,2)}}, @{Name='TotalGB';Expression={[math]::Round($_.Used/1GB + $_.Free/1GB,2)}} | Export-Csv -Path "$PSScriptRoot/disk-space-$(Get-Date -Format yyyyMMdd_HHmmss).csv" -NoTypeInformation
Write-Output "Exported disk info to disk-space-*.csv"
