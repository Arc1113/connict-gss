[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Version,
    [string]$RepositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path,
    [string]$RuntimeInputDirectory = (Join-Path $PSScriptRoot '..\runtime-input'),
    [string]$RuntimeManifest = (Join-Path $PSScriptRoot '..\runtime-input\runtime-manifest.json')
)

$ErrorActionPreference = 'Stop'
if ($Version -notmatch '^\d+\.\d+\.\d+([-.][0-9A-Za-z.-]+)?$') { throw 'Version must be semantic, for example 1.0.0.' }
if (-not (Test-Path -LiteralPath $RuntimeInputDirectory) -or -not (Test-Path -LiteralPath $RuntimeManifest)) {
    throw 'Windows runtime inputs and a verified runtime-manifest.json are required before packaging.'
}

function Assert-NativeCommandSucceeded([string]$Command) {
    if ($LASTEXITCODE -ne 0) {
        throw "$Command failed with exit code $LASTEXITCODE."
    }
}

function Assert-VerifiedRuntime([string]$Root, [string]$ManifestPath) {
    $manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
    if ($manifest.schema_version -ne 1 -or $manifest.platform -ne 'windows-x64' -or $manifest.artifacts.Count -eq 0) {
        throw 'The runtime manifest is invalid or is not for Windows x64.'
    }

    foreach ($artifact in $manifest.artifacts) {
        if ($artifact.path -notmatch '^[A-Za-z0-9._/-]+$' -or $artifact.sha256 -notmatch '^[a-f0-9]{64}$' -or $artifact.source_url -notmatch '^https://') {
            throw "Runtime manifest entry '$($artifact.path)' is incomplete or unsafe."
        }

        $path = Join-Path $Root ($artifact.path -replace '/', '\\')
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            throw "Required runtime artifact is missing: $($artifact.path)"
        }

        $actual = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($actual -ne $artifact.sha256) {
            throw "Runtime artifact checksum does not match its manifest: $($artifact.path)"
        }
    }

    $manifestPaths = @($manifest.artifacts | ForEach-Object { $_.path } | Sort-Object -Unique)
    $stagedPaths = @(Get-ChildItem -LiteralPath $Root -Recurse -File |
        ForEach-Object { $_.FullName.Substring($Root.TrimEnd('\\').Length).TrimStart('\\') -replace '\\', '/' } |
        Where-Object { $_ -notin @('runtime-manifest.json', 'runtime-provenance.json') } |
        Sort-Object -Unique)
    if (Compare-Object -ReferenceObject $manifestPaths -DifferenceObject $stagedPaths) {
        throw 'Every staged Windows runtime file must appear exactly once in runtime-manifest.json.'
    }

    $requiredArtifacts = @(
        'frankenphp/frankenphp.exe',
        'frankenphp/php.exe',
        'postgres/bin/createdb.exe',
        'postgres/bin/initdb.exe',
        'postgres/bin/pg_ctl.exe',
        'postgres/bin/pg_dump.exe',
        'postgres/bin/postgres.exe',
        'postgres/bin/psql.exe',
        'tts/tts-server.exe',
        'tts/bundle-manifest.json',
        'tts/THIRD_PARTY_NOTICES.txt',
        'tts/tools/lame.exe',
        'winsw/WinSW-x64.exe'
    )
    $missingArtifacts = @($requiredArtifacts | Where-Object { $_ -notin $manifestPaths })
    if ($missingArtifacts.Count -gt 0) {
        throw "The verified runtime is incomplete. Missing: $($missingArtifacts -join ', ')"
    }

    $requiredDirectories = @('tts/models', 'tts/tools/espeak-ng', 'tts/licenses')
    $missingDirectories = @($requiredDirectories | Where-Object {
        -not (Test-Path -LiteralPath (Join-Path $Root ($_ -replace '/', '\')) -PathType Container)
    })
    if ($missingDirectories.Count -gt 0) {
        throw "The verified offline TTS runtime is incomplete. Missing directories: $($missingDirectories -join ', ')"
    }
}

Assert-VerifiedRuntime -Root $RuntimeInputDirectory -ManifestPath $RuntimeManifest
Set-Location $RepositoryRoot
$commit = (git rev-parse HEAD).Trim()
Assert-NativeCommandSucceeded 'git rev-parse HEAD'
$worktreeChanges = @(git status --porcelain --untracked-files=all)
Assert-NativeCommandSucceeded 'git status'
if ($worktreeChanges.Count -gt 0) {
    throw 'Release packaging requires a clean Git worktree so the recorded commit exactly identifies the installer source.'
}
npm ci
Assert-NativeCommandSucceeded 'npm ci'
npm run build
Assert-NativeCommandSucceeded 'npm run build'
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
Assert-NativeCommandSucceeded 'composer install'
php artisan config:clear
Assert-NativeCommandSucceeded 'php artisan config:clear'
php artisan route:cache
Assert-NativeCommandSucceeded 'php artisan route:cache'
php artisan view:cache
Assert-NativeCommandSucceeded 'php artisan view:cache'
$payload = Join-Path $RepositoryRoot 'distribution\windows\payload'
Remove-Item -LiteralPath $payload -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path (Join-Path $payload 'app'), (Join-Path $payload 'runtime'), (Join-Path $payload 'config') -Force | Out-Null
robocopy $RepositoryRoot (Join-Path $payload 'app') /E /XD .git node_modules tests storage .browser-e2e-results playwright-report test-results distribution docker /XF .env .env.* | Out-Null
if ($LASTEXITCODE -gt 7) { throw 'Failed to stage the Laravel application payload.' }
robocopy $RuntimeInputDirectory (Join-Path $payload 'runtime') /E | Out-Null
if ($LASTEXITCODE -gt 7) { throw 'Failed to stage the verified Windows runtime payload.' }
robocopy (Join-Path $RepositoryRoot 'distribution\windows\config') (Join-Path $payload 'config') /E | Out-Null
if ($LASTEXITCODE -gt 7) { throw 'Failed to stage the Windows configuration templates.' }
$runtimeEnvironment = Join-Path $payload 'config\runtime.env.example'
$runtimeEnvironmentContents = Get-Content -LiteralPath $runtimeEnvironment -Raw
$runtimeEnvironmentContents = $runtimeEnvironmentContents.Replace('__GSS_RELEASE_VERSION__', $Version)
$runtimeEnvironmentContents = $runtimeEnvironmentContents.Replace('__GSS_BUILD_COMMIT__', $commit)
Set-Content -LiteralPath $runtimeEnvironment -Value $runtimeEnvironmentContents -NoNewline
$manifestTarget = Join-Path $payload 'runtime-manifest.json'
Copy-Item -LiteralPath $RuntimeManifest -Destination $manifestTarget -Force
@{ version = $Version; commit = $commit; built_at = [DateTime]::UtcNow.ToString('o') } |
    ConvertTo-Json |
    Set-Content -LiteralPath (Join-Path $payload 'release-manifest.json') -NoNewline
dotnet publish distribution\windows\launcher\ConnictGSS.csproj -c Release -o distribution\windows\launcher\publish
Assert-NativeCommandSucceeded 'dotnet publish'
$env:CONNICT_GSS_VERSION = $Version
& iscc distribution\windows\installer\connict-gss.iss
Assert-NativeCommandSucceeded 'iscc'
$installer = Join-Path $RepositoryRoot "distribution\windows\installer\Output\ConnictGSS-Setup-$Version.exe"
if (-not (Test-Path -LiteralPath $installer -PathType Leaf)) {
    throw "Inno Setup completed without producing the expected installer: $installer"
}
Get-FileHash -LiteralPath $installer -Algorithm SHA256 | Format-List
