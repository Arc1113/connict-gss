<?php

namespace Tests\Feature\Phase10;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentConfigurationTest extends TestCase
{
    private function readProjectFile(string $path): string
    {
        return File::get(base_path($path));
    }

    public function test_deployment_environment_example_disables_debug_and_uses_lan_oriented_reverb_settings(): void
    {
        $env = $this->readProjectFile('.env.deployment.example');

        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('APP_BIND_HOST=0.0.0.0', $env);
        $this->assertStringContainsString('APP_URL=https://', $env);
        $this->assertStringContainsString('GSS_HTTPS_HOST=', $env);
        $this->assertStringContainsString('HTTP_FORWARD_PORT=80', $env);
        $this->assertStringContainsString('REVERB_BROWSER_SCHEME=https', $env);
        $this->assertStringContainsString('REVERB_HOST=reverb', $env);
        $this->assertStringContainsString('REVERB_FORWARD_PORT=8080', $env);
        $this->assertStringContainsString('REVERB_BROWSER_HOST=', $env);
        $this->assertStringContainsString('REVERB_BROWSER_PORT="${HTTPS_FORWARD_PORT}"', $env);
        $this->assertStringNotContainsString('VITE_REVERB_', $env);
        $this->assertStringNotContainsString('APP_URL=http://localhost', $env);
        $this->assertStringNotContainsString('APP_DEBUG=true', $env);
    }

    public function test_postgresql_stays_loopback_only_and_app_reverb_ports_are_documented(): void
    {
        $compose = $this->readProjectFile('compose.yaml');
        $deploy = $this->readProjectFile('compose.deploy.yaml');
        $networkPlan = $this->readProjectFile('docs/network-plan.md');

        $this->assertStringContainsString('127.0.0.1:${DB_FORWARD_PORT:-54329}:5432', $compose);
        $this->assertStringContainsString('${APP_BIND_HOST:-127.0.0.1}:${APP_FORWARD_PORT:-8000}:8000', $compose);
        $this->assertStringContainsString('${APP_BIND_HOST:-127.0.0.1}:${REVERB_FORWARD_PORT:-8080}:8080', $compose);
        $this->assertStringContainsString('restart: unless-stopped', $deploy);
        $this->assertStringContainsString('caddy:2.10-alpine', $deploy);
        $this->assertStringContainsString('${HTTPS_FORWARD_PORT:-443}:443', $deploy);
        $this->assertStringContainsString('${HTTP_FORWARD_PORT:-80}:80', $deploy);
        $this->assertStringContainsString('/connect-phone/local-ca.crt', $this->readProjectFile('docker/caddy/Caddyfile'));
        $this->assertStringContainsString('redir {$GSS_APP_URL}{uri} permanent', $this->readProjectFile('docker/caddy/Caddyfile'));
        $this->assertStringContainsString('default_sni {$GSS_HTTPS_HOST}', $this->readProjectFile('docker/caddy/Caddyfile'));
        $this->assertStringNotContainsString('vite:', $deploy);
        $this->assertStringContainsString('TCP `8000`', $networkPlan);
        $this->assertStringContainsString('TCP `8080`', $networkPlan);
        $this->assertStringContainsString('PostgreSQL must not be reachable', $networkPlan);
    }

    public function test_php_upload_limits_support_the_validated_local_media_package(): void
    {
        $uploads = $this->readProjectFile('docker/php/uploads.ini');
        $developmentImage = $this->readProjectFile('docker/php/Dockerfile');
        $deploymentImage = $this->readProjectFile('docker/php/Dockerfile.deploy');

        $this->assertStringContainsString('upload_max_filesize=256M', $uploads);
        $this->assertStringContainsString('post_max_size=260M', $uploads);
        $this->assertStringContainsString('COPY docker/php/uploads.ini', $developmentImage);
        $this->assertStringContainsString('COPY docker/php/uploads.ini', $deploymentImage);
    }

    public function test_php_images_include_native_postgresql_backup_clients(): void
    {
        $developmentImage = $this->readProjectFile('docker/php/Dockerfile');
        $deploymentImage = $this->readProjectFile('docker/php/Dockerfile.deploy');
        $backupManager = $this->readProjectFile('app/Support/BackupManager.php');

        foreach ([$developmentImage, $deploymentImage] as $image) {
            $this->assertStringContainsString('FROM postgres:16-bookworm AS postgres-client', $image);
            $this->assertStringContainsString('/usr/local/bin/pg_dump', $image);
            $this->assertStringContainsString('/usr/local/bin/psql', $image);
            $this->assertStringContainsString('/usr/local/bin/createdb', $image);
            $this->assertStringContainsString('/usr/local/bin/dropdb', $image);
        }

        $this->assertStringContainsString("'pg_dump'", $backupManager);
        $this->assertStringNotContainsString("'docker',", $backupManager);
        $this->assertStringNotContainsString("'compose',", $backupManager);

        $restoreVerifier = $this->readProjectFile('app/Support/RestoreVerifier.php');
        $this->assertStringContainsString("'createdb'", $restoreVerifier);
        $this->assertStringContainsString("'dropdb'", $restoreVerifier);
        $this->assertStringNotContainsString("'docker',", $restoreVerifier);
        $this->assertStringNotContainsString("'compose',", $restoreVerifier);
    }

    public function test_offline_tts_temp_mount_allows_the_bundled_espeak_fallback(): void
    {
        $compose = $this->readProjectFile('compose.yaml');
        $localImage = $this->readProjectFile('docker/local-tts/Dockerfile');

        $this->assertStringContainsString('espeak-ng', $localImage);
        $this->assertStringContainsString('/tmp:size=256m,mode=1777,exec', $compose);
        $this->assertStringContainsString('read_only: true', $compose);
        $this->assertStringContainsString('HF_HUB_OFFLINE: "1"', $compose);
    }

    public function test_deployment_scripts_have_help_text_and_avoid_destructive_commands(): void
    {
        $scripts = [
            'scripts/gss-start.sh',
            'scripts/gss-stop.sh',
            'scripts/gss-health-check.sh',
            'scripts/gss-build-release.sh',
            'scripts/gss-export-local-ca.sh',
            'scripts/gss-install-service.sh',
            'scripts/gss-launch.sh',
        ];

        foreach ($scripts as $script) {
            $contents = $this->readProjectFile($script);

            $this->assertStringContainsString('set -Eeuo pipefail', $contents);
            $this->assertStringContainsString('--help', $contents);
            $this->assertStringNotContainsString('migrate:fresh', $contents);
            $this->assertStringNotContainsString('down -v', $contents);

            exec('bash '.escapeshellarg(base_path($script)).' --help', $output, $exitCode);

            $this->assertSame(0, $exitCode, $script.' --help should succeed');
        }
    }

    public function test_deployment_startup_fails_clearly_when_environment_values_are_placeholders(): void
    {
        $envFile = tempnam(sys_get_temp_dir(), 'gss-phase10-env-');
        File::put(
            $envFile,
            str_replace(
                'APP_KEY=',
                'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                $this->readProjectFile('.env.deployment.example')
            )
        );

        exec('bash '.escapeshellarg(base_path('scripts/gss-start.sh')).' --env-file '.escapeshellarg($envFile).' --skip-migrate 2>&1', $output, $exitCode);

        File::delete($envFile);

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Deployment secrets must be set', implode("\n", $output));
    }

    public function test_desktop_service_installer_supports_project_paths_with_spaces(): void
    {
        $installer = $this->readProjectFile('scripts/gss-install-service.sh');

        $this->assertStringNotContainsString('WorkingDirectory="%s"', $installer);
        $this->assertStringContainsString(
            'ExecStart=/usr/bin/env bash "%s/scripts/gss-start.sh"',
            $installer,
        );
        $this->assertStringContainsString('graduation-stage-system.desktop', $installer);
    }

    public function test_required_deployment_device_and_network_procedures_are_documented(): void
    {
        $deployment = $this->readProjectFile('docs/deployment.md');
        $deviceSetup = $this->readProjectFile('docs/device-setup.md');
        $networkPlan = $this->readProjectFile('docs/network-plan.md');
        $pwa = $this->readProjectFile('docs/pwa-and-autostart.md');

        $this->assertStringContainsString('Startup workflow', $deployment);
        $this->assertStringContainsString('Shutdown workflow', $deployment);
        $this->assertStringContainsString('Health check workflow', $deployment);
        $this->assertStringContainsString('Migration deployment policy', $deployment);
        $this->assertStringContainsString('Phase 11 boundary', $deployment);
        $this->assertStringContainsString('Scanner laptop', $deviceSetup);
        $this->assertStringContainsString('Stage laptop', $deviceSetup);
        $this->assertStringContainsString('Stage-control placement', $deviceSetup);
        $this->assertStringContainsString('Audio-device preparation', $deviceSetup);
        $this->assertStringContainsString('Power preparation', $deviceSetup);
        $this->assertStringContainsString('Network fallback order', $networkPlan);
        $this->assertStringContainsString('Cold standby network switch', $networkPlan);
        $this->assertStringContainsString('No Vite development port is required', $networkPlan);
        $this->assertStringContainsString('automatic server startup', $pwa);
        $this->assertStringContainsString('does not cache authenticated Inertia pages', $pwa);
    }

    public function test_deployment_secrets_backups_and_visual_evidence_are_excluded_from_artifacts(): void
    {
        $dockerignore = $this->readProjectFile('.dockerignore');
        $gitignore = $this->readProjectFile('.gitignore');
        $storageGitignore = $this->readProjectFile('storage/app/.gitignore');

        $this->assertStringContainsString('.env', $dockerignore);
        $this->assertStringContainsString('storage/app/gss-backups', $dockerignore);
        $this->assertStringContainsString('docs/evidence/', $dockerignore);
        $this->assertStringContainsString('playwright-report', $dockerignore);
        $this->assertStringContainsString('public/hot', $dockerignore);
        $this->assertStringContainsString('.env.production', $gitignore);
        $this->assertStringContainsString('/docs/evidence/**/*.png', $gitignore);
        $this->assertStringContainsString('*', $storageGitignore);
    }
}
