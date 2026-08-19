[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
foreach ($name in 'ConnictGSS-PostgreSQL', 'ConnictGSS-TTS', 'ConnictGSS-Web', 'ConnictGSS-Reverb', 'ConnictGSS-Queue') {
    $service = Get-Service -Name $name -ErrorAction SilentlyContinue
    if ($null -ne $service -and $service.Status -ne 'Running') {
        Start-Service -Name $name
    }
}
