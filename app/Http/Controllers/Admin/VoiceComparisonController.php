<?php

namespace App\Http\Controllers\Admin;

use App\Actions\GenerateVoiceComparison;
use App\Exceptions\VoiceComparisonException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateVoiceComparisonRequest;
use App\Models\Ceremony;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class VoiceComparisonController extends Controller
{
    public function store(
        GenerateVoiceComparisonRequest $request,
        Ceremony $ceremony,
        GenerateVoiceComparison $generator,
    ): RedirectResponse {
        try {
            $samples = $generator->generate(
                $ceremony,
                $request->validated('voices'),
                $request->user(),
                $request->ip(),
            );
        } catch (VoiceComparisonException $exception) {
            throw ValidationException::withMessages([
                'voice_comparison' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            count($samples).' temporary fictional voice sample(s) generated.',
        );
    }
}
