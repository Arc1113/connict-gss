[CmdletBinding()]
param(
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS'
)

$ErrorActionPreference = 'Stop'
& (Join-Path $InstallRoot 'scripts\Stop-ConnictGssServices.ps1')
& (Join-Path $InstallRoot 'scripts\Start-ConnictGssServices.ps1')
