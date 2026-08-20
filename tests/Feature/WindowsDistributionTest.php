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
        $this->assertStringContainsString('REVERB_SERVER_HOST=127.0.0.1', $runtime);
        $this->assertStringContainsString('LOCAL_TTS_URL=http://127.0.0.1:8880', $runtime);
        $this->assertStringContainsString('__GSS_HTTPS_HOST__', $caddy);
        $this->assertStringContainsString('http://__GSS_HTTPS_HOST__', $caddy);
        $this->assertStringContainsString('WriteAllText', $configureCaddy);
        $this->assertStringContainsString('Set-ConnictGssFirewallRules.ps1', $installer);
    }

    public function test_first_run_and_upgrade_scripts_use_controlled_workflows(): void
    {
        $setup = File::get(base_path('distribution/windows/scripts/Initialize-ConnictGss.ps1'));
        $upgrade = File::get(base_path('distribution/windows/scripts/Upgrade-ConnictGss.ps1'));
        $snapshot = File::get(base_path('distribution/windows/scripts/Snapshot-ConnictGssRuntime.ps1'));
        $launcher = File::get(base_path('distribution/windows/launcher/Program.cs'));
        $setupWizard = File::get(base_path('distribution/windows/launcher/SetupWizard.cs'));
        $network = File::get(base_path('distribution/windows/scripts/Update-ConnictGssNetworkConfiguration.ps1'));
        $runtimeBuild = File::get(base_path('distribution/windows/scripts/Build-ConnictGssRelease.ps1'));
        $runtimeBootstrap = File::get(base_path('distribution/windows/scripts/Get-ConnictGssRuntimeInputs.ps1'));
        $runtimeManifest = File::get(base_path('distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1'));
        $ttsRegistration = File::get(base_path('distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1'));
        $ttsBuild = File::get(base_path('distribution/windows/scripts/Build-ConnictGssOfflineTts.ps1'));
        $diagnostics = File::get(base_path('distribution/windows/scripts/Export-ConnictGssDiagnostics.ps1'));
        $cleanMachine = File::get(base_path('distribution/windows/scripts/Test-ConnictGssCleanMachine.ps1'));

        $this->assertStringContainsString('gss:bootstrap-install', $setup);
        $this->assertStringContainsString('migrate --force', $setup);
        $this->assertStringContainsString('GSS_INITIAL_ADMIN_PASSWORD', $setup);
        $this->assertStringContainsString('gss:backup', $upgrade);
        $this->assertStringContainsString('migrate --force', $upgrade);
        $this->assertStringContainsString('$Mode -eq \'Prepare\'', $upgrade);
        $this->assertStringNotContainsString('migrate:fresh', $setup.$upgrade);
        $this->assertStringContainsString('ProtectedData]::Unprotect', $setup);
        $this->assertStringContainsString('new SetupWizard', $launcher);
        $this->assertStringContainsString('ProtectedData.Protect', $setupWizard);
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
        $this->assertStringContainsString("Join-Path \$PSScriptRoot '..\\..\\..'", $runtimeBuild);
        $this->assertStringContainsString("Join-Path \$PSScriptRoot '..\\..\\..'", $ttsBuild);
        $this->assertStringNotContainsString("Join-Path \$PSScriptRoot '..\\..\\..\\..'", $runtimeBuild.$ttsBuild);
        $this->assertStringContainsString('Assert-NativeCommandSucceeded', $runtimeBuild);
        $this->assertStringContainsString('git status --porcelain --untracked-files=all', $runtimeBuild);
        $this->assertStringContainsString('tts/tts-server.exe', $runtimeBuild);
        $this->assertStringContainsString('tts/models', $runtimeBuild);
        $this->assertStringContainsString('tts/tools/espeak-ng', $runtimeBuild);
        $this->assertStringContainsString('winsw/WinSW-x64.exe', $runtimeBuild);
        $this->assertStringContainsString('runtime-manifest.json', $runtimeBuild);
        $this->assertStringContainsString('d01e39f9aec75cf9522830e424d9f76f081c1d8b5ec4cc0897a7fdccc8391bb7', $runtimeBootstrap);
        $this->assertStringContainsString('25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e', $runtimeBootstrap);
        $this->assertStringContainsString('runtime-provenance.json', $runtimeManifest);
        $this->assertStringContainsString('tts-server.exe', $ttsRegistration);
        $this->assertStringContainsString('HF_HUB_OFFLINE', $ttsBuild);
        $this->assertStringContainsString('LicenseDirectory', $ttsBuild);
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
