<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ReverbBrowserConfiguration
{
    /** @return array{key: string, host: string, port: int, scheme: string} */
    public function forRequest(Request $request): array
    {
        $configuredHost = trim((string) config('reverb.browser.host'));
        $host = $configuredHost === '' ? $request->getHost() : $configuredHost;
        $port = (int) config('reverb.browser.port', 8080);
        $scheme = strtolower(trim((string) config('reverb.browser.scheme', 'http')));
        $scheme = match ($scheme) {
            'ws' => 'http',
            'wss' => 'https',
            default => $scheme,
        };
        $key = trim((string) config('reverb.apps.apps.0.key'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = $request->isSecure() ? 'https' : 'http';
        }

        if ($port < 1 || $port > 65535) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        return compact('key', 'host', 'port', 'scheme');
    }

    /** @return array<string, mixed> */
    public function diagnostics(Request $request): array
    {
        $browser = $this->forRequest($request);
        $allowedOrigins = (array) config('reverb.apps.apps.0.allowed_origins', []);
        $originAllowed = in_array($request->getHost(), $allowedOrigins, true);
        $serverHost = (string) config('reverb.servers.reverb.hostname', 'reverb');
        $serverPort = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen($serverHost, $serverPort, $errorCode, $errorMessage, 0.25);
        $serviceReachable = is_resource($socket);

        if ($serviceReachable) {
            fclose($socket);
        }

        $websocketScheme = $browser['scheme'] === 'https' ? 'wss' : 'ws';
        $defaultPort = $browser['scheme'] === 'https' ? 443 : 80;
        $authority = $browser['host'].(
            $browser['port'] === $defaultPort ? '' : ':'.$browser['port']
        );

        return [
            'endpoint' => $websocketScheme.'://'.$authority,
            'host_source' => trim((string) config('reverb.browser.host')) === ''
                ? 'Current page hostname'
                : 'Configured hostname',
            'origin' => $request->getHost(),
            'origin_allowed' => $originAllowed,
            'service_reachable' => $serviceReachable,
            'configured' => $browser['key'] !== '' && $originAllowed,
            'guidance' => $this->guidance(
                $browser['key'] !== '',
                $originAllowed,
                $serviceReachable,
            ),
        ];
    }

    private function guidance(
        bool $hasKey,
        bool $originAllowed,
        bool $serviceReachable,
    ): string {
        if (! $hasKey) {
            return 'Set REVERB_APP_KEY before opening live operation screens.';
        }

        if (! $originAllowed) {
            return 'Add this page hostname to REVERB_ALLOWED_ORIGINS and restart Reverb.';
        }

        if (! $serviceReachable) {
            return 'The Reverb service is configured but not reachable from Laravel.';
        }

        return 'Runtime browser configuration and the Reverb service are ready.';
    }
}
