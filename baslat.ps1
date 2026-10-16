# Yerel gelistirme ortamini baslatir: MySQL (calismiyorsa) + Laravel sunucusu.
# Kullanim (PowerShell, proje klasorunde):
#   powershell -ExecutionPolicy Bypass -File .\baslat.ps1

$ErrorActionPreference = 'Continue'
$mysqlBin = 'C:\tools\mysql84\bin'
$project = $PSScriptRoot

& "$mysqlBin\mysqladmin.exe" -uroot ping 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host 'MySQL baslatiliyor...'
    Start-Process -FilePath "$mysqlBin\mysqld.exe" -ArgumentList '--defaults-file=C:/tools/mysql84/my.ini' -WindowStyle Hidden
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 1
        & "$mysqlBin\mysqladmin.exe" -uroot ping 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
    }
    if ($LASTEXITCODE -ne 0) { Write-Host 'MySQL baslatilamadi.' -ForegroundColor Red; exit 1 }
}
Write-Host 'MySQL calisiyor.' -ForegroundColor Green

Set-Location $project

# E-posta kuyrugu: ayri, kucultulmus pencerede calisir. Durdurmak icin o pencereyi kapatin.
$worker = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -like '*queue:work*' }
if (-not $worker) {
    Start-Process -FilePath 'php' -ArgumentList 'artisan', 'queue:work', '--tries=3', '--sleep=3' -WorkingDirectory $project -WindowStyle Minimized
    Write-Host 'E-posta kuyrugu baslatildi (kucultulmus pencere).' -ForegroundColor Green
} else {
    Write-Host 'E-posta kuyrugu zaten calisiyor.' -ForegroundColor Green
}

Write-Host 'Uygulama: http://localhost:8000  (durdurmak icin Ctrl+C)' -ForegroundColor Green
php artisan serve
