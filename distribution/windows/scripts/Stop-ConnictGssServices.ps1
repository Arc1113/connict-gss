[CmdletBinding()]
param()

$ErrorActionPreference = 'Continue'
foreach ($name in 'ConnictGSS-Queue', 'ConnictGSS-Reverb', 'ConnictGSS-Web', 'ConnictGSS-TTS', 'ConnictGSS-PostgreSQL') {
    Stop-Service -Name $name -ErrorAction SilentlyContinue
}
