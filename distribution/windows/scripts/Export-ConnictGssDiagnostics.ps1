[CmdletBinding()]
param(
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS',
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS',
    [string]$Destination
)

$ErrorActionPreference = 'Stop'
if (-not $Destination) { $Destination = Join-Path $env:USERPROFILE 'Desktop' }
if (-not (Test-Path -LiteralPath $Destination -PathType Container)) { throw 'The requested diagnostics destination does not exist.' }

$workspace = Join-Path ([IO.Path]::GetTempPath()) ('connict-gss-diagnostics-' + [guid]::NewGuid().ToString('N'))
$archive = Join-Path $Destination ('ConnictGSS-Diagnostics-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
New-Item -ItemType Directory -Path $workspace -Force | Out-Null

function Copy-SafeTextFile([string]$Source, [string]$Target) {
    if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { return }
    $contents = Get-Content -LiteralPath $Source -Raw
    $contents = [regex]::Replace($contents, '(?im)^\s*(APP_KEY|DB_PASSWORD|REVERB_APP_KEY|REVERB_APP_SECRET|AZURE_SPEECH_KEY)\s*=.*$', '$1=[REDACTED]')
    [IO.File]::WriteAllText($Target, $contents, [Text.UTF8Encoding]::new($false))
}

try {
    & (Join-Path $InstallRoot 'scripts\Test-ConnictGssHealth.ps1') -DataRoot $DataRoot |
        Set-Content -LiteralPath (Join-Path $workspace 'health.json') -NoNewline
    Get-ComputerInfo | Select-Object WindowsProductName, WindowsVersion, OsBuildNumber, CsSystemType |
        ConvertTo-Json | Set-Content -LiteralPath (Join-Path $workspace 'windows.json') -NoNewline
    Get-Service -Name 'ConnictGSS-*' -ErrorAction SilentlyContinue |
        Select-Object Name, Status, StartType | ConvertTo-Json |
        Set-Content -LiteralPath (Join-Path $workspace 'services.json') -NoNewline
    Copy-SafeTextFile (Join-Path $DataRoot 'status.json') (Join-Path $workspace 'status.json')
    Copy-SafeTextFile (Join-Path $InstallRoot 'release-manifest.json') (Join-Path $workspace 'release-manifest.json')
    Copy-SafeTextFile (Join-Path $InstallRoot 'runtime-manifest.json') (Join-Path $workspace 'runtime-manifest.json')

    # Do not include runtime.env, database/media/backup contents, QR values, or
    # raw logs. Their presence could disclose institutional or credential data.
    Compress-Archive -LiteralPath (Get-ChildItem -LiteralPath $workspace -File | Select-Object -ExpandProperty FullName) -DestinationPath $archive -Force
} finally {
    Remove-Item -LiteralPath $workspace -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Output $archive
