[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Version,
    [Parameter(Mandatory)][string]$SourceUrl,
    [Parameter(Mandatory)][string]$LicenseRecord,
    [Parameter(Mandatory)][string]$Verification,
    [string]$RuntimeInputDirectory = (Join-Path $PSScriptRoot '..\runtime-input')
)

$ErrorActionPreference = 'Stop'
if ($SourceUrl -notmatch '^https://') { throw 'The TTS source URL must use HTTPS.' }
if ($Verification -notmatch 'sha256|attestation') { throw 'The TTS verification record must include an archive SHA-256 or release attestation.' }
$ttsDirectory = Join-Path $RuntimeInputDirectory 'tts'
if (-not (Test-Path -LiteralPath $ttsDirectory -PathType Container)) { throw 'Stage the complete offline TTS bundle in runtime-input\tts before registering it.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'tts-server.exe') -PathType Leaf)) { throw 'The offline TTS bundle must contain tts-server.exe.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'models') -PathType Container)) { throw 'The offline TTS bundle must contain its model cache.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'licenses') -PathType Container)) { throw 'The offline TTS bundle must contain third-party licenses.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'licenses\license-inventory.json') -PathType Leaf)) { throw 'The offline TTS bundle must contain its generated license inventory.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'THIRD_PARTY_NOTICES.txt') -PathType Leaf)) { throw 'The offline TTS bundle must contain THIRD_PARTY_NOTICES.txt.' }
if (-not (Test-Path -LiteralPath (Join-Path $ttsDirectory 'bundle-manifest.json') -PathType Leaf)) { throw 'The offline TTS bundle must contain its build manifest.' }

$provenancePath = Join-Path $RuntimeInputDirectory 'runtime-provenance.json'
if (-not (Test-Path -LiteralPath $provenancePath)) { throw 'Run Get-ConnictGssRuntimeInputs.ps1 before registering the TTS bundle.' }
$provenance = Get-Content -LiteralPath $provenancePath -Raw | ConvertFrom-Json
$sources = @($provenance.sources | Where-Object { $_.prefix -ne 'tts/' })
$sources += [pscustomobject]@{
    prefix = 'tts/'
    version = $Version
    source_url = $SourceUrl
    license = $LicenseRecord
    verification = $Verification
}
@{ schema_version = 1; sources = $sources } |
    ConvertTo-Json -Depth 5 |
    Set-Content -LiteralPath $provenancePath -NoNewline
Write-Output $provenancePath
