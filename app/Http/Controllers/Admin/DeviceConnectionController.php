<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrCodeRenderer;
use App\Support\DeviceConnectionDetails;
use App\Support\ReverbBrowserConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DeviceConnectionController extends Controller
{
    public function __invoke(
        Request $request,
        DeviceConnectionDetails $connections,
        QrCodeRenderer $qrCodes,
        ReverbBrowserConfiguration $reverb,
    ): Response {
        Gate::authorize('manage-device-connections');

        $details = $connections->get();

        return Inertia::render('Admin/DeviceConnections/Index', [
            'connection' => [
                ...$details,
                'onboarding_qr_data_uri' => $details['enrollment_ready']
                    ? $qrCodes->dataUri($details['onboarding_url'])
                    : null,
            ],
            'realtime' => $reverb->diagnostics($request),
        ]);
    }
}
