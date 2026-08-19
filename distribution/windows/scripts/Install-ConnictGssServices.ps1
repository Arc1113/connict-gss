[CmdletBinding()]
param(
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS'
)

$ErrorActionPreference = 'Stop'
$serviceRoot = Join-Path $InstallRoot 'services'
$wrapper = Join-Path $InstallRoot 'runtime\winsw\WinSW-x64.exe'

& (Join-Path $InstallRoot 'scripts\Protect-ConnictGssData.ps1')

if (-not (Test-Path -LiteralPath $wrapper)) {
    throw 'Connict GSS service runtime is missing. Reinstall Connict GSS.'
}

foreach ($name in 'web', 'reverb', 'queue', 'tts') {
    $xml = Join-Path $serviceRoot "connict-gss-$name.xml"
    $target = Join-Path $serviceRoot "ConnictGSS-$name.exe"
    $serviceName = "ConnictGSS-$($name.Substring(0, 1).ToUpperInvariant())$($name.Substring(1))"
    if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {
        & $target stop | Out-Null
        & $target uninstall | Out-Null
    }
    Copy-Item -LiteralPath $wrapper -Destination $target -Force
    Copy-Item -LiteralPath $xml -Destination ([IO.Path]::ChangeExtension($target, '.xml')) -Force
    & $target install
    if ($LASTEXITCODE -ne 0) { throw "Could not install Connict GSS service: $name" }
    & sc.exe config $serviceName obj= 'NT AUTHORITY\LocalService' password= '' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Could not restrict Connict GSS service identity: $name" }
}

# PostgreSQL is registered separately by Initialize-ConnictGss.ps1 because its
# data cluster is created only once under ProgramData.
