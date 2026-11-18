param(
    [switch]$NoStart
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$workers = Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object {
    $_.CommandLine -match 'artisan\s+(queue:work|schedule:work)'
}

foreach ($worker in $workers) {
    Stop-Process -Id $worker.ProcessId -Force -ErrorAction SilentlyContinue
    Write-Host "stop php pid $($worker.ProcessId)"
}

Start-Sleep -Seconds 2

if (-not $NoStart) {
    Start-Process -FilePath 'powershell.exe' `
        -ArgumentList @('-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'start-clipflow.ps1')) `
        -WorkingDirectory $root

    Write-Host 'Worker dan scheduler dijalankan ulang.' -ForegroundColor Green
}
