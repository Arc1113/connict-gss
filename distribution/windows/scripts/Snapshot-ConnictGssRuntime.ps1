[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$InstallRoot,
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath (Join-Path $InstallRoot 'app'))) { exit 0 }

$version = 'unknown'
$releaseManifest = Join-Path $InstallRoot 'release-manifest.json'
if (Test-Path -LiteralPath $releaseManifest) {
    try { $version = (Get-Content -LiteralPath $releaseManifest -Raw | ConvertFrom-Json).version } catch { $version = 'unknown' }
}
$snapshot = Join-Path $DataRoot ('releases\' + $version + '-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $snapshot -Force | Out-Null

foreach ($directory in 'app', 'runtime', 'config', 'services', 'scripts') {
    $source = Join-Path $InstallRoot $directory
    if (-not (Test-Path -LiteralPath $source -PathType Container)) { continue }
    robocopy $source (Join-Path $snapshot $directory) /E /COPY:DAT /DCOPY:DAT /R:2 /W:1 | Out-Null
    if ($LASTEXITCODE -gt 7) { throw "Could not preserve the existing Connict GSS $directory runtime." }
}
foreach ($file in 'ConnictGSS.exe', 'release-manifest.json', 'runtime-manifest.json') {
    $source = Join-Path $InstallRoot $file
    if (Test-Path -LiteralPath $source -PathType Leaf) { Copy-Item -LiteralPath $source -Destination (Join-Path $snapshot $file) -Force }
}
@{ version = $version; created_at = [DateTime]::UtcNow.ToString('o'); source = $InstallRoot } |
    ConvertTo-Json | Set-Content -LiteralPath (Join-Path $snapshot 'snapshot.json') -NoNewline
Write-Output $snapshot
