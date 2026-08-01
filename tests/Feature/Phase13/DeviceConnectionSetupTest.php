<?php

namespace Tests\Feature\Phase13;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeviceConnectionSetupTest extends TestCase
{
    use RefreshDatabase;

    private string $certificatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->certificatePath = tempnam(sys_get_temp_dir(), 'gss-public-ca-');
        file_put_contents($this->certificatePath, "test-public-certificate\n");

        config()->set('app.url', 'https://192.168.50.10');
        config()->set('device-connections.http_port', 80);
        config()->set('device-connections.local_ca_path', $this->certificatePath);
    }

    protected function tearDown(): void
    {
        if (isset($this->certificatePath) && is_file($this->certificatePath)) {
            unlink($this->certificatePath);
        }

        parent::tearDown();
    }

    public function test_administrators_and_technical_operators_can_open_device_connections(): void
    {
        foreach ([UserRole::Administrator, UserRole::TechnicalOperator] as $role) {
            $operator = User::factory()->role($role)->create();

            $this->actingAs($operator)
                ->get('/admin/device-connections')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/DeviceConnections/Index')
                    ->where('connection.onboarding_url', 'http://192.168.50.10/connect-phone')
                    ->where('connection.scanner_url', 'https://192.168.50.10/scanner')
                    ->where('connection.certificate_url', 'http://192.168.50.10/connect-phone/local-ca.crt')
                    ->where('connection.certificate_available', true)
                    ->where('connection.secure_application', true)
                    ->where('connection.enrollment_ready', true)
                    ->where('connection.certificate_check_code', 'CA5B A76D F89F')
                    ->has('connection.certificate_fingerprint')
                    ->where(
                        'connection.onboarding_qr_data_uri',
                        fn (string $value): bool => str_starts_with(
                            $value,
                            'data:image/svg+xml;base64,',
                        ),
                    ));
        }
    }

    public function test_other_operational_roles_cannot_open_device_connections(): void
    {
        foreach ([
            UserRole::DataEncoder,
            UserRole::ScannerOperator,
            UserRole::StageOperator,
        ] as $role) {
            $operator = User::factory()->role($role)->create();

            $this->actingAs($operator)
                ->get('/admin/device-connections')
                ->assertForbidden();
        }
    }

    public function test_public_onboarding_contains_only_safe_local_setup_information(): void
    {
        $response = $this->get('/connect-phone');

        $response
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee('Connect this phone')
            ->assertSee('http://192.168.50.10/connect-phone/local-ca.crt', false)
            ->assertSee('https://192.168.50.10/scanner', false)
            ->assertSee('CA5B A76D F89F')
            ->assertDontSee('APP_KEY')
            ->assertDontSee('REVERB_APP_SECRET');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_public_certificate_download_is_not_cached(): void
    {
        $response = $this->get('/connect-phone/local-ca.crt');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-x509-ca-cert')
            ->assertDownload('gss-local-ca.crt');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );

        $this->assertSame(
            "test-public-certificate\n",
            file_get_contents($response->baseResponse->getFile()->getPathname()),
        );
    }

    public function test_certificate_download_fails_closed_when_public_certificate_is_missing(): void
    {
        unlink($this->certificatePath);

        $this->get('/connect-phone/local-ca.crt')->assertServiceUnavailable();
    }

    public function test_nonstandard_http_port_is_included_in_the_setup_address(): void
    {
        config()->set('device-connections.http_port', 8088);
        $operator = User::factory()->role(UserRole::TechnicalOperator)->create();

        $this->actingAs($operator)
            ->get('/admin/device-connections')
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'connection.onboarding_url',
                    'http://192.168.50.10:8088/connect-phone',
                ));
    }
}
