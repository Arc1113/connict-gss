[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$LameExecutable,
    [Parameter(Mandatory)][string]$EspeakDirectory,
    [Parameter(Mandatory)][string]$LicenseDirectory,
    [string]$RepositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path,
    [string]$RuntimeInputDirectory = (Join-Path $PSScriptRoot '..\runtime-input'),
    [string]$PythonCommand = 'python'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $LameExecutable -PathType Leaf)) { throw 'Supply a verified Windows x64 lame.exe path.' }
if (-not (Test-Path -LiteralPath $EspeakDirectory -PathType Container)) { throw 'Supply a verified Windows x64 eSpeak-NG runtime directory.' }
if (-not (Test-Path -LiteralPath $LicenseDirectory -PathType Container)) { throw 'Supply the reviewed third-party license directory.' }
if (-not (Test-Path -LiteralPath (Join-Path $LicenseDirectory 'THIRD_PARTY_NOTICES.txt') -PathType Leaf)) { throw 'LicenseDirectory must contain THIRD_PARTY_NOTICES.txt.' }

$workspace = Join-Path ([IO.Path]::GetTempPath()) ('connict-gss-tts-build-' + [guid]::NewGuid().ToString('N'))
$target = Join-Path $RuntimeInputDirectory 'tts'
$modelRevision = 'f3ff3571791e39611d31c381e3a41a3af07b4987'
New-Item -ItemType Directory -Path $workspace, $RuntimeInputDirectory -Force | Out-Null

try {
    & $PythonCommand --version
    if ($LASTEXITCODE -ne 0) { throw 'Python 3.12 x64 is required on the Windows release runner.' }
    & $PythonCommand -m venv (Join-Path $workspace 'venv')
    $python = Join-Path $workspace 'venv\Scripts\python.exe'
    & $python -m pip install --upgrade pip==25.2 wheel==0.45.1 pyinstaller==6.16.0
    & $python -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.6.0
    & $python -m pip install -r (Join-Path $RepositoryRoot 'docker\local-tts\requirements.txt')
    & $python -m pip install https://github.com/explosion/spacy-models/releases/download/en_core_web_sm-3.8.0/en_core_web_sm-3.8.0-py3-none-any.whl
    if ($LASTEXITCODE -ne 0) { throw 'Could not install the pinned offline TTS build dependencies.' }

    $modelHome = Join-Path $workspace 'models'
    $env:HF_HOME = $modelHome
    $env:HF_HUB_DISABLE_TELEMETRY = '1'
    & $python -c "from huggingface_hub import snapshot_download; snapshot_download(repo_id='hexgrad/Kokoro-82M', revision='$modelRevision')"
    if ($LASTEXITCODE -ne 0) { throw 'Could not download the pinned Kokoro model for the release bundle.' }

    $application = Join-Path $workspace 'application'
    New-Item -ItemType Directory -Path $application -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $RepositoryRoot 'docker\local-tts\server.py') -Destination (Join-Path $application 'server.py')
    & (Join-Path $workspace 'venv\Scripts\pyinstaller.exe') --noconfirm --clean --onedir --name tts-server --collect-all kokoro --collect-all misaki --collect-all spacy (Join-Path $application 'server.py')
    if ($LASTEXITCODE -ne 0) { throw 'PyInstaller could not create the offline TTS executable.' }

    if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
    New-Item -ItemType Directory -Path $target -Force | Out-Null
    robocopy (Join-Path $workspace 'dist\tts-server') $target /E | Out-Null
    if ($LASTEXITCODE -gt 7) { throw 'Could not stage the packaged TTS runtime.' }
    robocopy $modelHome (Join-Path $target 'models') /E | Out-Null
    if ($LASTEXITCODE -gt 7) { throw 'Could not stage the pinned Kokoro model cache.' }
    New-Item -ItemType Directory -Path (Join-Path $target 'tools') -Force | Out-Null
    Copy-Item -LiteralPath $LameExecutable -Destination (Join-Path $target 'tools\lame.exe') -Force
    Copy-Item -LiteralPath $EspeakDirectory -Destination (Join-Path $target 'tools\espeak-ng') -Recurse -Force
    Copy-Item -LiteralPath $LicenseDirectory -Destination (Join-Path $target 'licenses') -Recurse -Force
    Copy-Item -LiteralPath (Join-Path $LicenseDirectory 'THIRD_PARTY_NOTICES.txt') -Destination (Join-Path $target 'THIRD_PARTY_NOTICES.txt') -Force

    $env:HF_HOME = Join-Path $target 'models'
    $env:HF_HUB_OFFLINE = '1'
    $env:TRANSFORMERS_OFFLINE = '1'
    $env:LAME_BINARY = Join-Path $target 'tools\lame.exe'
    $env:PATH = "$(Join-Path $target 'tools\espeak-ng');$env:PATH"
    $server = Start-Process -FilePath (Join-Path $target 'tts-server.exe') -ArgumentList '--host', '127.0.0.1', '--port', '18880' -PassThru
    try {
        $ready = $false
        for ($attempt = 0; $attempt -lt 45; $attempt++) {
            try {
                $health = Invoke-WebRequest -Uri 'http://127.0.0.1:18880/health' -UseBasicParsing -TimeoutSec 2 | ConvertFrom-Json
                if ($health.model_revision -eq $modelRevision) { $ready = $true; break }
            } catch { Start-Sleep -Seconds 1 }
        }
        if (-not $ready) { throw 'The offline TTS bundle did not become healthy with network access disabled.' }
        foreach ($voice in @('af_heart', 'af_bella')) {
            $request = @{ text = 'Connict GSS offline speech verification.'; voice = $voice; speed = 1.0; pause = 0.1; sections = @() } | ConvertTo-Json -Compress
            $response = Invoke-WebRequest -Uri 'http://127.0.0.1:18880/v1/synthesize' -Method Post -ContentType 'application/json' -Body $request -UseBasicParsing -TimeoutSec 45
            if ($response.Headers['Content-Type'] -notlike 'audio/mpeg*' -or $response.RawContentLength -le 0) {
                throw "The offline TTS bundle did not produce MP3 output for $voice."
            }
        }
    } finally {
        if (-not $server.HasExited) { Stop-Process -Id $server.Id -Force }
    }

    @{
        engine = 'Kokoro'
        model_revision = $modelRevision
        built_at = [DateTime]::UtcNow.ToString('o')
        python = (& $python --version 2>&1)
        torch = (& $python -c 'import torch; print(torch.__version__)')
        kokoro = (& $python -c 'import kokoro; print(kokoro.__version__)')
        offline_health_check = 'passed'
        offline_synthesis_check = 'af_heart,af_bella passed'
    } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $target 'bundle-manifest.json') -NoNewline
} finally {
    Remove-Item -LiteralPath $workspace -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Output $target
