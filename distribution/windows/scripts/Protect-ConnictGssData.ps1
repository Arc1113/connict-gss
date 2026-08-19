[CmdletBinding()]
param(
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
$protectedDirectories = 'database', 'storage', 'backups', 'certificates', 'configuration', 'logs', 'caddy', 'releases'

New-Item -ItemType Directory -Path $DataRoot -Force | Out-Null
foreach ($name in $protectedDirectories) {
    $directory = Join-Path $DataRoot $name
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
    & icacls.exe $directory /inheritance:r /grant:r 'BUILTIN\Administrators:(OI)(CI)F' 'NT AUTHORITY\SYSTEM:(OI)(CI)F' 'NT AUTHORITY\LOCAL SERVICE:(OI)(CI)M' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Could not secure Connict GSS data directory: $directory" }
}

# The launcher reads only this non-sensitive status document. Runtime secrets
# remain in configuration\runtime.env, which is not readable by operators.
$status = Join-Path $DataRoot 'status.json'
if (-not (Test-Path -LiteralPath $status)) {
    Set-Content -LiteralPath $status -Value '{"initialized":false}' -NoNewline
}
& icacls.exe $status /inheritance:r /grant:r 'BUILTIN\Administrators:F' 'NT AUTHORITY\SYSTEM:F' 'NT AUTHORITY\LOCAL SERVICE:M' 'BUILTIN\Users:R' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not secure the Connict GSS status document.' }
