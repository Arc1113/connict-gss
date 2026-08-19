[CmdletBinding()]
param([string]$DataRoot = ${env:ProgramData} + '\ConnictGSS')

$services = 'ConnictGSS-PostgreSQL', 'ConnictGSS-Web', 'ConnictGSS-Reverb', 'ConnictGSS-Queue', 'ConnictGSS-TTS'
$result = [ordered]@{ checked_at = [DateTime]::UtcNow.ToString('o'); services = @{}; web = $false; database = $false; reverb = $false; tts = $false; certificate = $false; storage_free_bytes = 0; latest_backup = $null; server_address = $null }
foreach ($name in $services) {
    $service = Get-Service -Name $name -ErrorAction SilentlyContinue
    $result.services[$name] = $null -ne $service -and $service.Status -eq 'Running'
}
$status = Join-Path $DataRoot 'status.json'
if (Test-Path -LiteralPath $status) {
    $address = (Get-Content -LiteralPath $status -Raw | ConvertFrom-Json).server_address
    $result.server_address = $address
    try { $result.web = (Invoke-WebRequest -Uri "$address/up" -UseBasicParsing -TimeoutSec 5).StatusCode -eq 200 } catch { $result.web = $false }
}
$result.database = Test-NetConnection -ComputerName 127.0.0.1 -Port 5432 -InformationLevel Quiet -WarningAction SilentlyContinue
$result.reverb = Test-NetConnection -ComputerName 127.0.0.1 -Port 8080 -InformationLevel Quiet -WarningAction SilentlyContinue
try { $result.tts = (Invoke-WebRequest -Uri 'http://127.0.0.1:8880/health' -UseBasicParsing -TimeoutSec 3).StatusCode -eq 200 } catch { $result.tts = $false }
$result.certificate = Test-Path -LiteralPath (Join-Path $DataRoot 'certificates\gss-local-ca.crt')
$backupRoot = Join-Path $DataRoot 'backups'
if (Test-Path -LiteralPath $backupRoot) { $result.latest_backup = (Get-ChildItem -LiteralPath $backupRoot -Directory | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1 -ExpandProperty Name) }
$result.storage_free_bytes = (Get-PSDrive -Name ([IO.Path]::GetPathRoot($DataRoot).TrimEnd(':','\'))).Free
[pscustomobject]$result | ConvertTo-Json -Depth 4
