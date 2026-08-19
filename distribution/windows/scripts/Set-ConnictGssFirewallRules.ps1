[CmdletBinding()]
param([switch]$Remove)

$ErrorActionPreference = 'Stop'
foreach ($name in 'Connict GSS HTTPS', 'Connict GSS Device Onboarding') {
    & netsh.exe advfirewall firewall delete rule name="$name" | Out-Null
}
if ($Remove) { exit 0 }

# HTTPS is the ceremony endpoint. HTTP serves only the short-lived local
# certificate onboarding redirect and is limited to the local subnet.
& netsh.exe advfirewall firewall add rule name='Connict GSS HTTPS' dir=in action=allow protocol=TCP localport=443 remoteip=localsubnet profile=any | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not create the Connict GSS HTTPS firewall rule.' }
& netsh.exe advfirewall firewall add rule name='Connict GSS Device Onboarding' dir=in action=allow protocol=TCP localport=80 remoteip=localsubnet profile=any | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not create the Connict GSS local-network firewall rules.' }
