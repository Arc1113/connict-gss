<?php

namespace App\Http\Controllers;

use App\Support\ActiveCeremonyResolver;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function __invoke(ActiveCeremonyResolver $ceremonies): Response
    {
        $resolution = $ceremonies->resolve();

        return Inertia::render('Scanner', [
            'ceremony' => $resolution->ceremony === null ? null : [
                'id' => $resolution->ceremony->id,
                'name' => $resolution->ceremony->name,
            ],
            'ceremonyStatus' => $resolution->status,
        ]);
    }
}
