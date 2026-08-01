<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrCodeRenderer;
use App\Support\DeviceConnectionDetails;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DeviceConnectionController extends Controller
{
    public function __invoke(
        DeviceConnectionDetails $connections,
        QrCodeRenderer $qrCodes,
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
        ]);
    }
}
