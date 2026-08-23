[CmdletBinding()]
param(
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
$source = Join-Path $DataRoot 'caddy\pki\authorities\local\root.crt'
$targetDirectory = Join-Path $DataRoot 'certificates'
$target = Join-Path $targetDirectory 'gss-local-ca.crt'

for ($attempt = 0; $attempt -lt 30; $attempt++) {
    if (Test-Path -LiteralPath $source) {
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
        Copy-Item -LiteralPath $source -Destination $target -Force

        $certificate = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($target)
        $trusted = Get-ChildItem Cert:\LocalMachine\Root |
            Where-Object { $_.Thumbprint -eq $certificate.Thumbprint }
        if (-not $trusted) {
            Import-Certificate -FilePath $target -CertStoreLocation Cert:\LocalMachine\Root | Out-Null
        }

        exit 0
    }

    Start-Sleep -Seconds 1
}

throw 'The local HTTPS certificate was not generated. Check the Connict GSS Web service diagnostics.'
