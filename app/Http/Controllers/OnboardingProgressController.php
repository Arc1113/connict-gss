<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOnboardingProgressRequest;
use App\Models\UserOnboardingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OnboardingProgressController extends Controller
{
    public function update(UpdateOnboardingProgressRequest $request, string $tourKey): JsonResponse
    {
        $definition = config("onboarding.tours.{$request->user()->role->value}");
        $data = $request->validated();
        $progress = DB::transaction(function () use ($request, $tourKey, $definition, $data): UserOnboardingProgress {
            return UserOnboardingProgress::query()->updateOrCreate([
                'user_id' => $request->user()->id,
                'tour_key' => $tourKey,
                'tour_version' => $definition['version'],
            ], [
                'status' => $data['status'],
                'current_step' => $data['current_step'],
                'completed_at' => $data['status'] === 'completed' ? now() : null,
                'skipped_at' => $data['status'] === 'skipped' ? now() : null,
            ]);
        }, 3);

        return response()->json([
            'progress' => [
                'status' => $progress->status,
                'current_step' => $progress->current_step,
                'version' => $progress->tour_version,
            ],
        ]);
    }

    public function reset(Request $request, string $tourKey): Response
    {
        $definition = config("onboarding.tours.{$request->user()->role->value}");
        abort_unless(is_array($definition) && $definition['key'] === $tourKey, 403);
        UserOnboardingProgress::query()->where([
            'user_id' => $request->user()->id,
            'tour_key' => $tourKey,
            'tour_version' => $definition['version'],
        ])->delete();

        return response()->noContent();
    }
}
