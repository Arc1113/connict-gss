[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$InstallRoot,
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
$environmentFile = Join-Path $DataRoot 'configuration\runtime.env'
if (-not (Test-Path -LiteralPath $environmentFile)) { throw 'Connict GSS has not completed first-run setup.' }

$lanAddress = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -First 1 -ExpandProperty IPAddress
if (-not $lanAddress) { throw 'No private LAN IPv4 address was detected. Connect to the ceremony network and try again.' }

function Set-EnvironmentValue([string]$Content, [string]$Name, [string]$Value): string {
    $pattern = "(?m)^$([regex]::Escape($Name))=.*$"
    $replacement = "$Name=$Value"
    if ([regex]::IsMatch($Content, $pattern)) { return [regex]::Replace($Content, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $replacement }) }
    return $Content.TrimEnd() + [Environment]::NewLine + $replacement + [Environment]::NewLine
}

$environment = Get-Content -LiteralPath $environmentFile -Raw
$environment = Set-EnvironmentValue $environment 'APP_URL' "https://$lanAddress"
$environment = Set-EnvironmentValue $environment 'REVERB_ALLOWED_ORIGINS' "localhost,127.0.0.1,$lanAddress"
[IO.File]::WriteAllText($environmentFile, $environment, [Text.UTF8Encoding]::new($false))
& (Join-Path $InstallRoot 'scripts\Write-ConnictGssRuntimeConfiguration.ps1') -InstallRoot $InstallRoot -ServerAddress "https://$lanAddress" -DataRoot $DataRoot
& (Join-Path $InstallRoot 'scripts\Stop-ConnictGssServices.ps1')
& (Join-Path $InstallRoot 'scripts\Start-ConnictGssServices.ps1')
& (Join-Path $InstallRoot 'scripts\Export-ConnictGssLocalCa.ps1') -DataRoot $DataRoot
Set-Content -LiteralPath (Join-Path $DataRoot 'status.json') -Value ('{"initialized":true,"server_address":"https://' + $lanAddress + '","network_reconfigured_at":"' + [DateTime]::UtcNow.ToString('o') + '"}') -NoNewline
& (Join-Path $InstallRoot 'scripts\Test-ConnictGssHealth.ps1') -DataRoot $DataRoot
