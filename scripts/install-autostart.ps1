param(
    [switch]$Remove,
    [switch]$StartNow
)

$ErrorActionPreference = 'Stop'
$startScript = Join-Path $PSScriptRoot 'start-clipflow.ps1'

$startupDir = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'
$startupFile = Join-Path $startupDir 'clipflow-workers.cmd'

if ($Remove) {
    if (Test-Path $startupFile) {
        Remove-Item $startupFile -Force
        Write-Host "Autostart dihapus: $startupFile" -ForegroundColor Yellow
    } else {
        Write-Host 'Autostart belum terpasang.' -ForegroundColor DarkGray
    }

    exit 0
}

if (-not (Test-Path $startScript)) {
    throw "Script tidak ditemukan: $startScript"
}

New-Item -ItemType Directory -Force $startupDir | Out-Null

$content = @"
@echo off
powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "$startScript"
"@

Set-Content -Path $startupFile -Value $content -Encoding ASCII

Write-Host "Autostart terpasang (tanpa admin): $startupFile" -ForegroundColor Green
Write-Host 'Worker pipeline, worker upload, dan scheduler akan mulai otomatis saat Anda login.' -ForegroundColor Green
Write-Host 'Hapus dengan: .\scripts\install-autostart.ps1 -Remove' -ForegroundColor DarkGray

if ($StartNow) {
    Start-Process -FilePath 'powershell.exe' `
        -ArgumentList @('-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', $startScript) `
        -WorkingDirectory (Split-Path -Parent $PSScriptRoot)

    Write-Host 'Worker dijalankan sekarang (hidden).' -ForegroundColor Green
}
