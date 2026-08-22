[CmdletBinding()]
param(
    [string]$RuntimeInputDirectory = (Join-Path $PSScriptRoot '..\runtime-input'),
    [string]$ProvenancePath = (Join-Path $PSScriptRoot '..\runtime-input\runtime-provenance.json'),
    [string]$ManifestPath = (Join-Path $PSScriptRoot '..\runtime-input\runtime-manifest.json')
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $ProvenancePath)) { throw 'runtime-provenance.json is required to generate a runtime manifest.' }
$RuntimeInputDirectory = (Resolve-Path -LiteralPath $RuntimeInputDirectory).Path
$provenance = Get-Content -LiteralPath $ProvenancePath -Raw | ConvertFrom-Json
if ($provenance.schema_version -ne 1 -or $provenance.sources.Count -eq 0) { throw 'The runtime provenance file is invalid.' }

$artifacts = foreach ($file in Get-ChildItem -LiteralPath $RuntimeInputDirectory -Recurse -File) {
    $relative = $file.FullName.Substring($RuntimeInputDirectory.TrimEnd('\\').Length).TrimStart('\\') -replace '\\', '/'
    if ($relative -in @('runtime-manifest.json', 'runtime-provenance.json')) { continue }
    $source = $provenance.sources | Where-Object { $relative.StartsWith($_.prefix, [StringComparison]::Ordinal) } | Select-Object -First 1
    if ($null -eq $source) { throw "No release provenance exists for staged runtime file: $relative" }
    [ordered]@{
        path = $relative
        version = $source.version
        sha256 = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        source_url = $source.source_url
        license = $source.license
        verification = $source.verification
    }
}

if ($artifacts.Count -eq 0) { throw 'No runtime artifacts were staged.' }
@{ schema_version = 1; platform = 'windows-x64'; artifacts = @($artifacts) } |
    ConvertTo-Json -Depth 6 |
    Set-Content -LiteralPath $ManifestPath -NoNewline
Write-Output $ManifestPath
