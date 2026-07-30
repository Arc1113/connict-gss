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
        $this->assertStringContainsString('REVERB_HOST=reverb', $env);
        $this->assertStringContainsString('REVERB_FORWARD_PORT=8080', $env);
        $this->assertStringContainsString('VITE_REVERB_HOST=', $env);
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
        $this->assertStringNotContainsString('vite:', $deploy);
        $this->assertStringContainsString('TCP `8000`', $networkPlan);
        $this->assertStringContainsString('TCP `8080`', $networkPlan);
        $this->assertStringContainsString('PostgreSQL must not be reachable', $networkPlan);
    }

    public function test_deployment_scripts_have_help_text_and_avoid_destructive_commands(): void
    {
        $scripts = [
            'scripts/gss-start.sh',
            'scripts/gss-stop.sh',
            'scripts/gss-health-check.sh',
            'scripts/gss-build-release.sh',
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

    public function test_required_deployment_device_and_network_procedures_are_documented(): void
    {
        $deployment = $this->readProjectFile('docs/deployment.md');
        $deviceSetup = $this->readProjectFile('docs/device-setup.md');
        $networkPlan = $this->readProjectFile('docs/network-plan.md');

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
    }

    public function test_deployment_secrets_backups_and_visual_evidence_are_excluded_from_artifacts(): void
    {
        $dockerignore = $this->readProjectFile('.dockerignore');
        $gitignore = $this->readProjectFile('.gitignore');
        $storageGitignore = $this->readProjectFile('storage/app/.gitignore');

        $this->assertStringContainsString('.env', $dockerignore);
        $this->assertStringContainsString('storage/app/gss-backups', $dockerignore);
        $this->assertStringContainsString('docs/evidence/**/*.png', $dockerignore);
        $this->assertStringContainsString('public/hot', $dockerignore);
        $this->assertStringContainsString('.env.production', $gitignore);
        $this->assertStringContainsString('/docs/evidence/**/*.png', $gitignore);
        $this->assertStringContainsString('*', $storageGitignore);
    }
}
