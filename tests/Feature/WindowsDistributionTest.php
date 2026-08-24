<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WindowsDistributionTest extends TestCase
{
    public function test_windows_distribution_keeps_runtime_and_institutional_data_separate(): void
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));
        $installer = File::get(base_path('distribution/windows/installer/connict-gss.iss'));
        $runtime = File::get(base_path('distribution/windows/config/runtime.env.example'));
        $caddy = File::get(base_path('distribution/windows/config/Caddyfile'));
        $configureCaddy = File::get(base_path('distribution/windows/scripts/Write-ConnictGssRuntimeConfiguration.ps1'));

        $this->assertStringContainsString('GSS_DATA_DIR', $bootstrap);
        $this->assertStringContainsString('useStoragePath', $bootstrap);
        $this->assertStringContainsString('preg_match', $bootstrap);
        $this->assertStringContainsString('{commonappdata}\\ConnictGSS\\database', $installer);
        $this->assertStringContainsString('{app}\\app', $installer);
        $this->assertStringNotContainsString('[UninstallDelete]', $installer);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $runtime);
        $this->assertStringContainsString('GSS_POSTGRES_BIN=', $runtime);
        $this->assertStringContainsString('REVERB_SERVER_HOST=127.0.0.1', $runtime);
        $this->assertStringContainsString('LOCAL_TTS_URL=http://127.0.0.1:8880', $runtime);
        $this->assertStringContainsString('__GSS_HTTPS_HOST__', $caddy);
        $this->assertStringContainsString('http://__GSS_HTTPS_HOST__', $caddy);
        $this->assertStringContainsString('WriteAllText', $configureCaddy);
        $this->assertStringContainsString("\$rendered = \$template.Replace('__GSS_HTTPS_HOST__'", $configureCaddy);
        $this->assertStringNotContainsString("\n    .Replace(", $configureCaddy);
        $this->assertStringContainsString('Set-ConnictGssFirewallRules.ps1', $installer);
        $this->assertStringContainsString("GetEnv('CONNICT_GSS_FAST_BUILD')", $installer);
    }

    public function test_first_run_and_upgrade_scripts_use_controlled_workflows(): void
    {
        $setup = File::get(base_path('distribution/windows/scripts/Initialize-ConnictGss.ps1'));
        $upgrade = File::get(base_path('distribution/windows/scripts/Upgrade-ConnictGss.ps1'));
        $snapshot = File::get(base_path('distribution/windows/scripts/Snapshot-ConnictGssRuntime.ps1'));
        $launcher = File::get(base_path('distribution/windows/launcher/Program.cs'));
        $setupWizard = File::get(base_path('distribution/windows/launcher/SetupWizard.cs'));
        $launcherProject = File::get(base_path('distribution/windows/launcher/ConnictGSS.csproj'));
        $network = File::get(base_path('distribution/windows/scripts/Update-ConnictGssNetworkConfiguration.ps1'));
        $installer = File::get(base_path('distribution/windows/installer/connict-gss.iss'));
        $runtimeBuild = File::get(base_path('distribution/windows/scripts/Build-ConnictGssRelease.ps1'));
        $releaseWorkflow = File::get(base_path('.github/workflows/windows-release.yml'));
        $runtimeBootstrap = File::get(base_path('distribution/windows/scripts/Get-ConnictGssRuntimeInputs.ps1'));
        $phpConfiguration = File::get(base_path('distribution/windows/config/php.ini'));
        $runtimeManifest = File::get(base_path('distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1'));
        $ttsRegistration = File::get(base_path('distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1'));
        $ttsBuild = File::get(base_path('distribution/windows/scripts/Build-ConnictGssOfflineTts.ps1'));
        $ttsServer = File::get(base_path('docker/local-tts/server.py'));
        $ttsLicenseInventory = File::get(base_path('docker/local-tts/export_license_inventory.py'));
        $diagnostics = File::get(base_path('distribution/windows/scripts/Export-ConnictGssDiagnostics.ps1'));
        $cleanMachine = File::get(base_path('distribution/windows/scripts/Test-ConnictGssCleanMachine.ps1'));

        $this->assertStringContainsString('gss:bootstrap-install', $setup);
        $this->assertStringContainsString('migrate --force', $setup);
        $this->assertStringContainsString('GSS_INITIAL_ADMIN_PASSWORD', $setup);
        $this->assertStringContainsString("'GSS_POSTGRES_BIN=' = \"GSS_POSTGRES_BIN='\$postgres'\"", $setup);
        $this->assertStringContainsString('RandomNumberGenerator]::Create()', $setup);
        $this->assertStringNotContainsString('RandomNumberGenerator]::Fill', $setup);
        $this->assertStringNotContainsString('[Convert]::ToHexString', $setup);
        $this->assertStringContainsString('gss:backup', $upgrade);
        $this->assertStringContainsString('migrate --force', $upgrade);
        $this->assertStringContainsString('$Mode -eq \'Prepare\'', $upgrade);
        $this->assertStringNotContainsString('migrate:fresh', $setup.$upgrade);
        $this->assertStringContainsString('ProtectedData]::Unprotect', $setup);
        $this->assertStringContainsString('new SetupWizard', $launcher);
        $this->assertStringContainsString('<ImplicitUsings>enable</ImplicitUsings>', $launcherProject);
        $this->assertStringNotContainsString("password= ''", File::get(base_path('distribution/windows/scripts/Install-ConnictGssServices.ps1')));
        $this->assertStringNotContainsString("password= ''", $setup);
        $this->assertStringNotContainsString('runtime\\vclibs', $setup);
        $this->assertStringContainsString('PostgreSQL cluster initialization failed', $setup);
        $this->assertStringContainsString('$initdbOutput', $setup);
        $this->assertStringContainsString('WindowsIdentity]::GetCurrent().User.Value', File::get(base_path('distribution/windows/scripts/Protect-ConnictGssData.ps1')));
        $this->assertStringContainsString('setup-error.json', $setup.$diagnostics);
        $this->assertStringContainsString('Connict GSS initialization failed:', $setup);
        $this->assertStringContainsString('vc_redist.x64.exe', $runtimeBootstrap);
        $this->assertStringContainsString('14.44.35211.0', $runtimeBootstrap);
        $this->assertStringContainsString('vc_redist.x64.exe', $installer);
        $this->assertStringContainsString('config\\php.ini', $installer);
        foreach (['openssl', 'pdo_pgsql', 'pgsql', 'intl', 'mbstring', 'zip'] as $extension) {
            $this->assertStringContainsString("extension = {$extension}", $phpConfiguration);
        }
        foreach (['reverb', 'queue', 'tts'] as $service) {
            $this->assertStringContainsString('<description>', File::get(base_path("distribution/windows/services/connict-gss-{$service}.xml")));
        }
        $this->assertStringContainsString('ProtectedData.Protect', $setupWizard);
        $this->assertStringContainsString('X509Store]::new', File::get(base_path('distribution/windows/scripts/Export-ConnictGssLocalCa.ps1')));
        $this->assertStringNotContainsString('Cert:\\LocalMachine', File::get(base_path('distribution/windows/scripts/Export-ConnictGssLocalCa.ps1')));
        $this->assertStringContainsString('File.Delete(requestPath)', $setupWizard);
        $this->assertStringContainsString('WaitForReadiness', $setupWizard);
        $this->assertStringContainsString('Test-ConnictGssHealth.ps1', $setupWizard);
        foreach (['web', 'database', 'reverb', 'tts', 'certificate'] as $healthCheck) {
            $this->assertStringContainsString("GetProperty(\"{$healthCheck}\")", $setupWizard);
        }
        $this->assertStringContainsString('Open Connict GSS', $setupWizard);
        $this->assertStringNotContainsString('GSS_INITIAL_ADMIN_PASSWORD"]', $launcher.$setupWizard);
        $this->assertStringNotContainsString('static string? Prompt', $launcher);
        $this->assertStringContainsString('REVERB_ALLOWED_ORIGINS', $network);
        $this->assertStringContainsString('Write-ConnictGssRuntimeConfiguration.ps1', $network);
        $this->assertStringContainsString('Assert-VerifiedRuntime', $runtimeBuild);
        $this->assertStringContainsString('$releaseParameters = @{', $releaseWorkflow);
        $this->assertStringContainsString('Build-ConnictGssRelease.ps1 @releaseParameters', $releaseWorkflow);
        $this->assertStringNotContainsString("@('-Version', \$env:RELEASE_VERSION)", $releaseWorkflow);
        $this->assertSame(2, substr_count($releaseWorkflow, "extensions: 'fileinfo, pgsql, mbstring, intl, zip, bcmath'"));
        $this->assertStringContainsString('Verify PHP packaging extensions', $releaseWorkflow);
        $this->assertStringContainsString('Required PHP extension is unavailable', $releaseWorkflow);
        $this->assertStringContainsString('__GSS_HTTPS_HOST__:8081', $releaseWorkflow);
        $this->assertStringContainsString('auto_https disable_redirects', $releaseWorkflow);
        $this->assertStringContainsString('"%BASE%\\..\\app\\artisan" reverb:start', File::get(base_path('distribution/windows/services/connict-gss-reverb.xml')));
        $this->assertStringContainsString('"%BASE%\\..\\app\\artisan" queue:work', File::get(base_path('distribution/windows/services/connict-gss-queue.xml')));
        $this->assertStringContainsString("foreach (\$relative in 'status.json', 'logs', 'diagnostics')", $releaseWorkflow);
        $this->assertStringContainsString('-Verb RunAs -Wait -PassThru', $releaseWorkflow);
        $this->assertStringContainsString('if ($initialization.ExitCode -ne 0)', $releaseWorkflow);
        $this->assertStringContainsString("\$env:CONNICT_GSS_FAST_BUILD = '1'", $releaseWorkflow);
        $this->assertStringContainsString("'pgAdmin 4', 'StackBuilder'", $runtimeBootstrap);
        $this->assertStringContainsString("Join-Path \$PSScriptRoot '..\\..\\..'", $runtimeBuild);
        $this->assertStringContainsString("Join-Path \$PSScriptRoot '..\\..\\..'", $ttsBuild);
        $this->assertStringNotContainsString("Join-Path \$PSScriptRoot '..\\..\\..\\..'", $runtimeBuild.$ttsBuild);
        $this->assertStringContainsString('Assert-NativeCommandSucceeded', $runtimeBuild);
        $this->assertStringContainsString('git status --porcelain --untracked-files=all', $runtimeBuild);
        $this->assertStringContainsString('tts/tts-server.exe', $runtimeBuild);
        $this->assertStringContainsString('tts/models', $runtimeBuild);
        $this->assertStringContainsString('tts/licenses/license-inventory.json', $runtimeBuild);
        $this->assertStringContainsString('winsw/WinSW-x64.exe', $runtimeBuild);
        $this->assertStringContainsString('runtime-manifest.json', $runtimeBuild);
        $this->assertStringContainsString('52fb7d1d8ca785599189789f813dd5cd2c29892ed2eaa3fdaab07e938e551870', $runtimeBootstrap);
        $this->assertStringContainsString('25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e', $runtimeBootstrap);
        $this->assertStringContainsString('runtime-provenance.json', $runtimeManifest);
        $this->assertStringContainsString('tts-server.exe', $ttsRegistration);
        $this->assertStringContainsString('HF_HUB_OFFLINE', $ttsBuild);
        $this->assertStringContainsString('--collect-all espeakng_loader', $ttsBuild);
        $this->assertStringContainsString('--collect-all lameenc', $ttsBuild);
        $this->assertStringContainsString('--collect-all en_core_web_sm', $ttsBuild);
        $this->assertStringContainsString('max_workers=1', $ttsBuild);
        $this->assertStringContainsString('--distpath', $ttsBuild);
        $this->assertStringContainsString('uvicorn.run(app', $ttsServer);
        $this->assertStringContainsString('license-inventory.json', $ttsLicenseInventory);
        $this->assertStringContainsString('af_bella', $ttsBuild);
        $this->assertStringContainsString('runtime.env', $diagnostics);
        $this->assertStringContainsString('Do not include runtime.env', $diagnostics);
        $this->assertStringContainsString('Snapshot-ConnictGssRuntime.ps1', $upgrade);
        $this->assertStringContainsString('robocopy', $snapshot);
        $this->assertStringContainsString('Export diagnostics', $launcher.$setupWizard);
        $this->assertStringContainsString('Restart-ConnictGssServices.ps1', $launcher);
        $this->assertStringContainsString('no database in application directory', $cleanMachine);
        $this->assertStringContainsString('offline TTS service', $cleanMachine);
        $this->assertStringContainsString('clean-machine-qa-', $cleanMachine);
    }
}
