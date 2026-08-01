<?php

namespace App\Http\Controllers;

use App\Support\DeviceConnectionDetails;
use Illuminate\Http\Response;

final class PhoneOnboardingController extends Controller
{
    public function __invoke(DeviceConnectionDetails $connections): Response
    {
        $response = response()->view(
            'phone-onboarding',
            ['connection' => $connections->get()],
        );

        return $response
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Robots-Tag', 'noindex, noarchive')
            ->header(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            );
    }
}
