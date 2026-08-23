[CmdletBinding()]
param(
    [string]$InstitutionName = $env:GSS_INITIAL_INSTITUTION_NAME,
    [string]$AdministratorName = $env:GSS_INITIAL_ADMIN_NAME,
    [string]$AdministratorEmail = $env:GSS_INITIAL_ADMIN_EMAIL,
    [string]$AdministratorPassword = $env:GSS_INITIAL_ADMIN_PASSWORD,
    [string]$SetupRequest,
    [string]$InstallRoot = ${env:ProgramFiles} + '\Connict GSS',
    [string]$DataRoot = ${env:ProgramData} + '\ConnictGSS'
)

$ErrorActionPreference = 'Stop'
trap {
    $initializationError = $_
    try {
        $setupLogDirectory = Join-Path $DataRoot 'logs'
        New-Item -ItemType Directory -Path $setupLogDirectory -Force | Out-Null
        [ordered]@{
            failed_at = [DateTime]::UtcNow.ToString('o')
            message = $initializationError.Exception.Message
            position = $initializationError.InvocationInfo.PositionMessage
            stack = $initializationError.ScriptStackTrace
        } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $setupLogDirectory 'setup-error.json') -NoNewline
    } catch { }
    [Console]::Error.WriteLine("Connict GSS initialization failed: $($initializationError.Exception.Message)")
    exit 1
}
if ($SetupRequest) {
    try {
        $encrypted = [IO.File]::ReadAllBytes($SetupRequest)
        $plain = [Security.Cryptography.ProtectedData]::Unprotect($encrypted, $null, [Security.Cryptography.DataProtectionScope]::CurrentUser)
        $request = [Text.Encoding]::UTF8.GetString($plain) | ConvertFrom-Json
        $InstitutionName = $request.institutionName
        $AdministratorName = $request.administratorName
        $AdministratorEmail = $request.administratorEmail
        $AdministratorPassword = $request.administratorPassword
    } finally {
        Remove-Item -LiteralPath $SetupRequest -Force -ErrorAction SilentlyContinue
    }
}
if ([string]::IsNullOrWhiteSpace($InstitutionName) -or [string]::IsNullOrWhiteSpace($AdministratorName) -or [string]::IsNullOrWhiteSpace($AdministratorEmail) -or [string]::IsNullOrWhiteSpace($AdministratorPassword)) {
    throw 'Initial setup details were not supplied. Open Connict GSS and complete the setup form.'
}
if (Test-Path -LiteralPath (Join-Path $DataRoot 'configuration\initialized.json')) {
    throw 'Connict GSS has already been initialized. Use the application settings to change administrator accounts.'
}
if ($AdministratorPassword.Length -lt 12) { throw 'The administrator password must contain at least 12 characters.' }

foreach ($directory in 'database', 'storage', 'backups', 'certificates', 'configuration', 'logs') {
    New-Item -ItemType Directory -Path (Join-Path $DataRoot $directory) -Force | Out-Null
}
& (Join-Path $InstallRoot 'scripts\Protect-ConnictGssData.ps1') -DataRoot $DataRoot
Remove-Item -LiteralPath (Join-Path $DataRoot 'logs\setup-error.json') -Force -ErrorAction SilentlyContinue

function New-Secret([int]$Bytes = 32) {
    $buffer = New-Object byte[] $Bytes
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($buffer)
    } finally {
        $generator.Dispose()
    }
    return ([BitConverter]::ToString($buffer) -replace '-', '').ToLowerInvariant()
}

$postgres = Join-Path $InstallRoot 'runtime\postgres\bin'
$php = Join-Path $InstallRoot 'runtime\frankenphp\php.exe'
$artisan = Join-Path $InstallRoot 'app\artisan'
$environmentFile = Join-Path $DataRoot 'configuration\runtime.env'
$databasePassword = New-Secret
$reverbKey = New-Secret 16
$reverbSecret = New-Secret
$appKeyBytes = New-Object byte[] 32
$appKeyGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $appKeyGenerator.GetBytes($appKeyBytes)
} finally {
    $appKeyGenerator.Dispose()
}
$appKey = 'base64:' + [Convert]::ToBase64String($appKeyBytes)
$lanAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -match '^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)' -and $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1 -ExpandProperty IPAddress)
if (-not $lanAddress) { throw 'No private LAN IPv4 address was detected. Connect the server laptop to the ceremony network and try again.' }

$template = Get-Content -LiteralPath (Join-Path $InstallRoot 'config\runtime.env.example') -Raw
$values = @{
    'APP_NAME="Connict GSS"' = ('APP_NAME="' + $InstitutionName.Replace('"', '') + ' — Connict GSS"')
    'APP_KEY=' = "APP_KEY=$appKey"
    'APP_URL=https://127.0.0.1' = "APP_URL=https://$lanAddress"
    'GSS_DATA_DIR=C:\ProgramData\ConnictGSS' = "GSS_DATA_DIR=$DataRoot"
    'DB_PASSWORD=' = "DB_PASSWORD=$databasePassword"
    'GSS_POSTGRES_BIN=' = "GSS_POSTGRES_BIN=$postgres"
    'REVERB_APP_KEY=' = "REVERB_APP_KEY=$reverbKey"
    'REVERB_APP_SECRET=' = "REVERB_APP_SECRET=$reverbSecret"
    'REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1' = "REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1,$lanAddress"
    'GSS_BACKUP_PATH=C:\ProgramData\ConnictGSS\backups' = "GSS_BACKUP_PATH=$(Join-Path $DataRoot 'backups')"
    'GSS_LOCAL_CA_PATH=C:\ProgramData\ConnictGSS\certificates\gss-local-ca.crt' = "GSS_LOCAL_CA_PATH=$(Join-Path $DataRoot 'certificates\gss-local-ca.crt')"
}
foreach ($entry in $values.GetEnumerator()) { $template = $template.Replace($entry.Key, $entry.Value) }
[IO.File]::WriteAllText($environmentFile, $template, [Text.UTF8Encoding]::new($false))
& (Join-Path $InstallRoot 'scripts\Write-ConnictGssRuntimeConfiguration.ps1') -InstallRoot $InstallRoot -ServerAddress "https://$lanAddress" -DataRoot $DataRoot

$cluster = Join-Path $DataRoot 'database'
if (-not (Test-Path -LiteralPath (Join-Path $cluster 'PG_VERSION'))) {
    $passwordFile = Join-Path $env:TEMP ('connict-postgres-' + [guid]::NewGuid().ToString('N') + '.txt')
    try {
        [IO.File]::WriteAllText($passwordFile, $databasePassword, [Text.UTF8Encoding]::new($false))
        $initdbOutput = @(& (Join-Path $postgres 'initdb.exe') -D $cluster -U postgres --pwfile=$passwordFile --auth-host=scram-sha-256 --auth-local=scram-sha-256 2>&1)
        if ($LASTEXITCODE -ne 0) {
            throw "PostgreSQL cluster initialization failed: $($initdbOutput -join ' ')"
        }
    } finally { Remove-Item -LiteralPath $passwordFile -Force -ErrorAction SilentlyContinue }
    Add-Content -LiteralPath (Join-Path $cluster 'postgresql.conf') "listen_addresses = '127.0.0.1'`nport = 5432"
    & (Join-Path $postgres 'pg_ctl.exe') register -N ConnictGSS-PostgreSQL -D $cluster -o '-h 127.0.0.1 -p 5432' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not register the Connict GSS PostgreSQL service.' }
    & sc.exe config ConnictGSS-PostgreSQL obj= 'NT AUTHORITY\LocalService' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not restrict the Connict GSS PostgreSQL service identity.' }
    Start-Service ConnictGSS-PostgreSQL
    $env:PGPASSWORD = $databasePassword
    & (Join-Path $postgres 'psql.exe') -h 127.0.0.1 -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE USER connict_gss WITH PASSWORD '$databasePassword';" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the Connict GSS PostgreSQL role.' }
    & (Join-Path $postgres 'createdb.exe') -h 127.0.0.1 -U postgres -O connict_gss connict_gss | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the Connict GSS PostgreSQL database.' }
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}

$env:GSS_DATA_DIR = $DataRoot
$env:GSS_ENV_FILE = $environmentFile
$env:GSS_INITIAL_ADMIN_NAME = $AdministratorName
$env:GSS_INITIAL_ADMIN_EMAIL = $AdministratorEmail
$env:GSS_INITIAL_ADMIN_PASSWORD = $AdministratorPassword
& $php $artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Database migrations failed. The installer preserved the database and logs for support.' }
& $php $artisan gss:bootstrap-install
if ($LASTEXITCODE -ne 0) { throw 'Initial administrator setup failed. No installer data was removed.' }
Remove-Item Env:GSS_INITIAL_ADMIN_NAME, Env:GSS_INITIAL_ADMIN_EMAIL, Env:GSS_INITIAL_ADMIN_PASSWORD -ErrorAction SilentlyContinue
Set-Content -LiteralPath (Join-Path $DataRoot 'status.json') -Value ('{"initialized":true,"installed_at":"' + [DateTime]::UtcNow.ToString('o') + '","server_address":"https://' + $lanAddress + '"}') -NoNewline
& (Join-Path $InstallRoot 'scripts\Start-ConnictGssServices.ps1')
& (Join-Path $InstallRoot 'scripts\Export-ConnictGssLocalCa.ps1') -DataRoot $DataRoot
