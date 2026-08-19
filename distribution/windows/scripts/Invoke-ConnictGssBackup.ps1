[CmdletBinding()]
param(
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS',
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
$environmentFile = Join-Path $DataRoot 'configuration\runtime.env'
if (-not (Test-Path -LiteralPath $environmentFile)) { throw 'Connict GSS has not completed first-run setup.' }

$env:GSS_DATA_DIR = $DataRoot
$env:GSS_ENV_FILE = $environmentFile
& (Join-Path $InstallRoot 'runtime\frankenphp\php.exe') (Join-Path $InstallRoot 'app\artisan') gss:backup
if ($LASTEXITCODE -ne 0) { throw 'Backup failed safely. Review diagnostics before retrying.' }
