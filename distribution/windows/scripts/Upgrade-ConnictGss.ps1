[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$InstallRoot,
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS',
    [ValidateSet('Prepare', 'Apply')][string]$Mode = 'Apply'
)

$ErrorActionPreference = 'Stop'
$environmentFile = Join-Path $DataRoot 'configuration\runtime.env'
if (-not (Test-Path -LiteralPath $environmentFile)) { exit 0 }

if ($Mode -eq 'Prepare') {
    & (Join-Path $InstallRoot 'scripts\Stop-ConnictGssServices.ps1')
}
$env:GSS_DATA_DIR = $DataRoot
$env:GSS_ENV_FILE = $environmentFile
$php = Join-Path $InstallRoot 'runtime\frankenphp\php.exe'
$artisan = Join-Path $InstallRoot 'app\artisan'
if ($Mode -eq 'Prepare') {
    & $php $artisan gss:backup
    if ($LASTEXITCODE -ne 0) { throw 'Upgrade stopped because the required verified backup failed.' }
    & (Join-Path $InstallRoot 'scripts\Snapshot-ConnictGssRuntime.ps1') -InstallRoot $InstallRoot -DataRoot $DataRoot | Out-Null
    exit 0
}
if (Test-Path -LiteralPath (Join-Path $DataRoot 'status.json')) {
    $serverAddress = (Get-Content -LiteralPath (Join-Path $DataRoot 'status.json') -Raw | ConvertFrom-Json).server_address
    & (Join-Path $InstallRoot 'scripts\Write-ConnictGssRuntimeConfiguration.ps1') -InstallRoot $InstallRoot -ServerAddress $serverAddress -DataRoot $DataRoot
}
& $php $artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Upgrade stopped because database migration failed. The verified backup and application logs were preserved.' }
& (Join-Path $InstallRoot 'scripts\Start-ConnictGssServices.ps1')
& (Join-Path $InstallRoot 'scripts\Export-ConnictGssLocalCa.ps1') -DataRoot $DataRoot
$health = & (Join-Path $InstallRoot 'scripts\Test-ConnictGssHealth.ps1') -DataRoot $DataRoot | ConvertFrom-Json
if (-not $health.database -or -not $health.web -or -not $health.reverb -or -not $health.certificate) {
    & (Join-Path $InstallRoot 'scripts\Stop-ConnictGssServices.ps1')
    throw 'Upgrade stopped because the application health check failed. The verified backup, prior runtime snapshot, and logs were preserved.'
}
