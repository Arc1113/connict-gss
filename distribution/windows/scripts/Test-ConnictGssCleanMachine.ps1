[CmdletBinding()]
param(
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS',
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS',
    [switch]$RequireTts
)

$ErrorActionPreference = 'Stop'

function Add-Check([string]$Name, [bool]$Passed, [string]$Detail, [bool]$Required = $true) {
    $script:checks += [pscustomobject]@{ name = $Name; passed = $Passed; required = $Required; detail = $Detail }
}

$checks = @()
$statusPath = Join-Path $DataRoot 'status.json'
$healthScript = Join-Path $InstallRoot 'scripts\Test-ConnictGssHealth.ps1'
$reportDirectory = Join-Path $DataRoot 'diagnostics'

Add-Check 'installed application' (Test-Path -LiteralPath (Join-Path $InstallRoot 'ConnictGSS.exe') -PathType Leaf) $InstallRoot
Add-Check 'persistent data root' (Test-Path -LiteralPath $DataRoot -PathType Container) $DataRoot
Add-Check 'no database in application directory' (-not (Test-Path -LiteralPath (Join-Path $InstallRoot 'database'))) 'Database data must remain in ProgramData.'
Add-Check 'no Laravel storage in application directory' (-not (Test-Path -LiteralPath (Join-Path $InstallRoot 'app\storage'))) 'Laravel storage must remain in ProgramData.'
Add-Check 'first-run setup completed' (Test-Path -LiteralPath $statusPath -PathType Leaf) $statusPath

$health = $null
if (Test-Path -LiteralPath $healthScript -PathType Leaf) {
    try { $health = & $healthScript -DataRoot $DataRoot | ConvertFrom-Json } catch { Add-Check 'health command' $false $_.Exception.Message }
} else {
    Add-Check 'health command' $false "Missing $healthScript"
}

if ($null -ne $health) {
    $serverAddress = if ([string]::IsNullOrWhiteSpace($health.server_address)) { 'No server address reported.' } else { $health.server_address }
    Add-Check 'PostgreSQL loopback service' ([bool]$health.database) 'PostgreSQL must be available only to local components.'
    Add-Check 'web service and HTTPS certificate' ([bool]$health.web -and [bool]$health.certificate) $serverAddress
    Add-Check 'Reverb loopback service' ([bool]$health.reverb) 'Realtime service must be available on loopback.'
    Add-Check 'queue worker service' ([bool]$health.services.'ConnictGSS-Queue') 'Queue worker must be supervised by Windows.'
    Add-Check 'offline TTS service' ([bool]$health.tts) 'TTS failure must not prevent core ceremony operation.' $RequireTts.IsPresent
    Add-Check 'backup directory' ($null -ne $health.latest_backup) 'Create and verify a backup before signing off the machine.'
}

foreach ($service in 'ConnictGSS-PostgreSQL', 'ConnictGSS-Web', 'ConnictGSS-Reverb', 'ConnictGSS-Queue') {
    $installed = $null -ne (Get-Service -Name $service -ErrorAction SilentlyContinue)
    Add-Check "managed service: $service" $installed 'Service must be installed before ceremony rehearsal.'
}
if ($RequireTts) {
    Add-Check 'managed service: ConnictGSS-TTS' ($null -ne (Get-Service -Name 'ConnictGSS-TTS' -ErrorAction SilentlyContinue)) 'TTS service is required for this acceptance run.'
}

New-Item -ItemType Directory -Path $reportDirectory -Force | Out-Null
$report = [ordered]@{
    checked_at = [DateTime]::UtcNow.ToString('o')
    computer_name = $env:COMPUTERNAME
    install_root = $InstallRoot
    data_root = $DataRoot
    server_address = if ($null -ne $health) { $health.server_address } else { $null }
    passed = -not ($checks | Where-Object { $_.required -and -not $_.passed })
    checks = $checks
}
$reportPath = Join-Path $reportDirectory ('clean-machine-qa-' + [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss') + '.json')
$report | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $reportPath -NoNewline
$checks | Format-Table -AutoSize | Out-Host
Write-Output "Clean-machine report: $reportPath"
if (-not $report.passed) { exit 1 }
