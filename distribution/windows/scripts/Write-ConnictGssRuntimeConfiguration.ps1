[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$InstallRoot,
    [Parameter(Mandatory)][string]$ServerAddress,
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
$uri = [Uri]$ServerAddress
if ($uri.Scheme -ne 'https' -or [string]::IsNullOrWhiteSpace($uri.Host)) {
    throw 'The Connict GSS server address must be a valid HTTPS address.'
}

$template = Get-Content -LiteralPath (Join-Path $InstallRoot 'config\Caddyfile') -Raw
$publicDirectory = Join-Path $InstallRoot 'app\public'
$caddyDataDirectory = Join-Path $DataRoot 'caddy'
$rendered = $template
    .Replace('__GSS_HTTPS_HOST__', $uri.Host)
    .Replace('__GSS_APP_PUBLIC__', $publicDirectory)
    .Replace('__GSS_CADDY_DATA__', $caddyDataDirectory)

New-Item -ItemType Directory -Path (Join-Path $DataRoot 'configuration'), $caddyDataDirectory -Force | Out-Null
[IO.File]::WriteAllText(
    (Join-Path $DataRoot 'configuration\Caddyfile'),
    $rendered,
    [Text.UTF8Encoding]::new($false)
)
