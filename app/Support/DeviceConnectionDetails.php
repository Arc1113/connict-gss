<?php

namespace App\Support;

use RuntimeException;

final class DeviceConnectionDetails
{
    /**
     * @return array{
     *     onboarding_url: string,
     *     scanner_url: string,
     *     certificate_url: string,
     *     certificate_available: bool,
     *     secure_application: bool,
     *     enrollment_ready: bool,
     *     certificate_fingerprint: string|null,
     *     certificate_check_code: string|null
     * }
     */
    public function get(): array
    {
        $applicationUrl = rtrim((string) config('app.url'), '/');
        $parts = parse_url($applicationUrl);

        if (
            ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new RuntimeException(
                'APP_URL must be a valid local HTTP or HTTPS address.',
            );
        }

        $host = str_contains($parts['host'], ':')
            ? '['.$parts['host'].']'
            : $parts['host'];
        $httpPort = (int) config('device-connections.http_port', 80);

        if ($httpPort < 1 || $httpPort > 65535) {
            throw new RuntimeException('HTTP_FORWARD_PORT must be a valid TCP port.');
        }
        $httpAuthority = $host.($httpPort === 80 ? '' : ':'.$httpPort);
        $onboardingUrl = 'http://'.$httpAuthority.'/connect-phone';
        $certificatePath = (string) config('device-connections.local_ca_path');
        $certificateAvailable = is_file($certificatePath) && is_readable($certificatePath);
        $fingerprint = $certificateAvailable
            ? hash_file('sha256', $certificatePath)
            : false;

        if ($fingerprint === false) {
            $fingerprint = null;
        }

        $secureApplication = ($parts['scheme'] ?? null) === 'https';

        return [
            'onboarding_url' => $onboardingUrl,
            'scanner_url' => $applicationUrl.'/scanner',
            'certificate_url' => $onboardingUrl.'/local-ca.crt',
            'certificate_available' => $certificateAvailable,
            'secure_application' => $secureApplication,
            'enrollment_ready' => $certificateAvailable && $secureApplication,
            'certificate_fingerprint' => $fingerprint === null
                ? null
                : strtoupper(implode(':', str_split($fingerprint, 2))),
            'certificate_check_code' => $fingerprint === null
                ? null
                : strtoupper(implode(' ', array_slice(str_split($fingerprint, 4), 0, 3))),
        ];
    }

    public function certificatePath(): string
    {
        return (string) config('device-connections.local_ca_path');
    }
}
