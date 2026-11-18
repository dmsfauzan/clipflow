param(
    [switch]$NoScheduler
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$logDir = Join-Path $root 'storage\logs'
New-Item -ItemType Directory -Force $logDir | Out-Null

$definitions = @(
    @{
        Name = 'worker-pipeline'
        Args = @('artisan', 'queue:work', '--queue=pipeline', '--timeout=7200', '--tries=1')
    },
    @{
        Name = 'worker-uploads'
        Args = @('artisan', 'queue:work', '--queue=uploads', '--timeout=3600', '--tries=1')
    }
)

if (-not $NoScheduler) {
    $definitions += @{
        Name = 'scheduler'
        Args = @('artisan', 'schedule:work')
    }
}

$processes = @{}

function Start-WorkerProcess {
    param($Definition)

    $stdout = Join-Path $logDir ($Definition.Name + '.out.log')
    $stderr = Join-Path $logDir ($Definition.Name + '.err.log')

    return Start-Process -FilePath 'php' -ArgumentList $Definition.Args -WorkingDirectory $root `
        -RedirectStandardOutput $stdout -RedirectStandardError $stderr -WindowStyle Hidden -PassThru
}

Write-Host 'clipflow workers starting. Tekan Ctrl+C untuk berhenti.' -ForegroundColor Green
Write-Host "Log: $logDir" -ForegroundColor DarkGray

try {
    while ($true) {
        foreach ($definition in $definitions) {
            $process = $processes[$definition.Name]

            if ($null -eq $process -or $process.HasExited) {
                $processes[$definition.Name] = Start-WorkerProcess -Definition $definition

                try {
                    $processes[$definition.Name].PriorityClass = 'BelowNormal'
                } catch {
                    # Prioritas tidak dapat diubah; abaikan.
                }

                Write-Host ("[{0}] start {1} (pid {2})" -f (Get-Date -Format 'HH:mm:ss'), $definition.Name, $processes[$definition.Name].Id)
            }
        }

        Start-Sleep -Seconds 10
    }
}
finally {
    foreach ($process in $processes.Values) {
        if ($null -ne $process -and -not $process.HasExited) {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        }
    }
}
