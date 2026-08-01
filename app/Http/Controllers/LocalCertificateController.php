<?php

namespace App\Http\Controllers;

use App\Support\DeviceConnectionDetails;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LocalCertificateController extends Controller
{
    public function __invoke(DeviceConnectionDetails $connections): BinaryFileResponse
    {
        $path = $connections->certificatePath();

        abort_unless(is_file($path) && is_readable($path), 503, 'The local certificate is not ready yet.');

        return response()
            ->download($path, 'gss-local-ca.crt', [
                'Content-Type' => 'application/x-x509-ca-cert',
                'Cache-Control' => 'no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex, noarchive',
            ]);
    }
}
