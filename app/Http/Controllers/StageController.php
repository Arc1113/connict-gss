<?php

namespace App\Http\Controllers;

use App\Support\ActiveCeremonyResolver;
use App\Support\StageStatePayload;
use App\Support\StageTheme;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class StageController extends Controller
{
    public function show(ActiveCeremonyResolver $ceremonies): Response
    {
        $resolution = $ceremonies->resolve();

        return Inertia::render('Stage', [
            'ceremony' => $resolution->ceremony === null ? null : [
                'id' => $resolution->ceremony->id,
                'name' => $resolution->ceremony->name,
                'theme' => StageTheme::forCeremony($resolution->ceremony),
            ],
        ]);
    }

    public function state(
        ActiveCeremonyResolver $ceremonies,
        StageStatePayload $payload,
    ): JsonResponse {
        $resolution = $ceremonies->resolve();

        if (! $resolution->isResolved()) {
            return response()->json([
                'status' => 'unavailable',
                'state' => [
                    'version' => 0,
                    'mode' => 'idle',
                    'presentation' => null,
                ],
            ], 409);
        }

        return response()->json($payload->forCeremony($resolution->ceremony));
    }
}
