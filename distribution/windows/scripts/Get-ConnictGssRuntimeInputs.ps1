[CmdletBinding()]
param(
    [string]$RuntimeInputDirectory = (Join-Path $PSScriptRoot '..\runtime-input')
)

$ErrorActionPreference = 'Stop'
$frankenPhpVersion = '1.12.7'
$frankenPhpArchive = 'frankenphp-windows-x86_64.zip'
$frankenPhpHash = '52fb7d1d8ca785599189789f813dd5cd2c29892ed2eaa3fdaab07e938e551870'
$frankenPhpExecutableHash = '6497ba22c7916fe757dc2979f3bedf10ec28b6e9bdb685bec25b8f9bb12fe18e'
$phpExecutableHash = '9151fca722979afbef1178adfdfee96f502fbc5f1c7e1227f42013c5dcd329ac'
$winSwVersion = '2.12.0'
$winSwHash = '05b82d46ad331cc16bdc00de5c6332c1ef818df8ceefcd49c726553209b3a0da'
$vcRuntimeVersion = '14.44.35211.0'
$vcRuntimeUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
$vcRuntimeHash = 'cc0ff0eb1dc3f5188ae6300faef32bf5beeba4bdd6e8e445a9184072096b713b'
$postgresVersion = '16.15-1'
$postgresArchive = 'postgresql-16.15-1-windows-x64-binaries.zip'
$postgresHash = '25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e'

function Get-VerifiedFile([string]$Uri, [string]$Destination, [string]$ExpectedHash) {
    Invoke-WebRequest -Uri $Uri -OutFile $Destination
    $actual = (Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $ExpectedHash) {
        Remove-Item -LiteralPath $Destination -Force -ErrorAction SilentlyContinue
        throw "Checksum verification failed for $Uri"
    }
}

New-Item -ItemType Directory -Path $RuntimeInputDirectory -Force | Out-Null
$temporaryDirectory = Join-Path ([IO.Path]::GetTempPath()) ('cg-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $temporaryDirectory -Force | Out-Null

try {
    $archive = Join-Path $temporaryDirectory $frankenPhpArchive
    Get-VerifiedFile "https://github.com/php/frankenphp/releases/download/v$frankenPhpVersion/$frankenPhpArchive" $archive $frankenPhpHash
    $frankenPhpTarget = Join-Path $RuntimeInputDirectory 'frankenphp'
    New-Item -ItemType Directory -Path $frankenPhpTarget -Force | Out-Null
    Expand-Archive -LiteralPath $archive -DestinationPath $frankenPhpTarget -Force

    foreach ($check in @(
        @{ Path = 'frankenphp.exe'; Hash = $frankenPhpExecutableHash },
        @{ Path = 'php.exe'; Hash = $phpExecutableHash }
    )) {
        $actual = (Get-FileHash -LiteralPath (Join-Path $frankenPhpTarget $check.Path) -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($actual -ne $check.Hash) { throw "Extracted FrankenPHP file did not match its approved checksum: $($check.Path)" }
    }

    $winSwTarget = Join-Path $RuntimeInputDirectory 'winsw'
    New-Item -ItemType Directory -Path $winSwTarget -Force | Out-Null
    Get-VerifiedFile "https://github.com/winsw/winsw/releases/download/v$winSwVersion/WinSW-x64.exe" (Join-Path $winSwTarget 'WinSW-x64.exe') $winSwHash

    # Install the supported Microsoft redistributable before any bundled PHP,
    # PostgreSQL, or TTS process starts. The older Desktop VCLibs APPX contains
    # VC 14.39 and cannot load the current PHP build, which requires VC 14.44.
    $legacyVclibs = Join-Path $RuntimeInputDirectory 'vclibs'
    if (Test-Path -LiteralPath $legacyVclibs) { Remove-Item -LiteralPath $legacyVclibs -Recurse -Force }
    $vcRuntimeTarget = Join-Path $RuntimeInputDirectory 'vcredist'
    if (Test-Path -LiteralPath $vcRuntimeTarget) { Remove-Item -LiteralPath $vcRuntimeTarget -Recurse -Force }
    New-Item -ItemType Directory -Path $vcRuntimeTarget -Force | Out-Null
    $vcRuntimeInstaller = Join-Path $vcRuntimeTarget 'vc_redist.x64.exe'
    Get-VerifiedFile $vcRuntimeUrl $vcRuntimeInstaller $vcRuntimeHash
    $vcRuntimeSignature = Get-AuthenticodeSignature -LiteralPath $vcRuntimeInstaller
    if ($vcRuntimeSignature.Status -ne 'Valid' -or $vcRuntimeSignature.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') {
        throw 'The Microsoft Visual C++ Redistributable signature is not valid.'
    }

    $postgresArchivePath = Join-Path $temporaryDirectory $postgresArchive
    Get-VerifiedFile "https://get.enterprisedb.com/postgresql/$postgresArchive" $postgresArchivePath $postgresHash
    Expand-Archive -LiteralPath $postgresArchivePath -DestinationPath $temporaryDirectory -Force
    $postgresTarget = Join-Path $RuntimeInputDirectory 'postgres'
    if (Test-Path -LiteralPath $postgresTarget) { Remove-Item -LiteralPath $postgresTarget -Recurse -Force }
    Move-Item -LiteralPath (Join-Path $temporaryDirectory 'pgsql') -Destination $postgresTarget
    foreach ($optionalComponent in 'pgAdmin 4', 'StackBuilder') {
        Remove-Item -LiteralPath (Join-Path $postgresTarget $optionalComponent) -Recurse -Force -ErrorAction SilentlyContinue
    }
    foreach ($binary in 'createdb.exe', 'initdb.exe', 'pg_ctl.exe', 'pg_dump.exe', 'postgres.exe', 'psql.exe') {
        if (-not (Test-Path -LiteralPath (Join-Path $postgresTarget "bin\\$binary") -PathType Leaf)) { throw "PostgreSQL archive is missing expected binary: $binary" }
    }
    @{
        schema_version = 1
        sources = @(
            @{ prefix = 'vcredist/'; version = $vcRuntimeVersion; source_url = $vcRuntimeUrl; license = 'Microsoft Visual C++ Redistributable terms'; verification = "sha256:$vcRuntimeHash; authenticode:Microsoft Corporation" },
            @{ prefix = 'frankenphp/'; version = $frankenPhpVersion; source_url = "https://github.com/php/frankenphp/releases/download/v$frankenPhpVersion/$frankenPhpArchive"; license = 'FrankenPHP and bundled PHP licenses; see included license and SBOM'; verification = "archive-sha256:$frankenPhpHash" },
            @{ prefix = 'winsw/'; version = $winSwVersion; source_url = "https://github.com/winsw/winsw/releases/download/v$winSwVersion/WinSW-x64.exe"; license = 'MIT'; verification = "sha256:$winSwHash" },
            @{ prefix = 'postgres/'; version = $postgresVersion; source_url = "https://get.enterprisedb.com/postgresql/$postgresArchive"; license = 'PostgreSQL'; verification = "archive-sha256:$postgresHash" }
        )
    } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $RuntimeInputDirectory 'runtime-provenance.json') -NoNewline
} finally {
    Remove-Item -LiteralPath $temporaryDirectory -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host 'FrankenPHP, PostgreSQL, and WinSW runtime inputs are staged and checksum-verified.'
Write-Host 'The offline TTS bundle still requires its release-specific attestation before packaging can proceed.'
