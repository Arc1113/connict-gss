<?php

namespace App\Http\Controllers;

use App\Actions\PresentStageQueueItem;
use App\Actions\ReplayCurrentAnnouncement;
use App\Actions\ReturnStageQueueItem;
use App\Actions\SetAutomaticPresentationPaused;
use App\Actions\SetStageHolding;
use App\Actions\SkipStageQueueItem;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageControlPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StageControlController extends Controller
{
    public function show(ActiveCeremonyResolver $ceremonies): Response
    {
        $resolution = $ceremonies->resolve();

        return Inertia::render('StageControl', [
            'ceremony' => $resolution->ceremony === null ? null : [
                'id' => $resolution->ceremony->id,
                'name' => $resolution->ceremony->name,
                'presentation_mode' => $resolution->ceremony
                    ->presentation_mode->value,
            ],
            'ceremonyStatus' => $resolution->status,
        ]);
    }

    public function queue(
        ActiveCeremonyResolver $ceremonies,
        StageControlPayload $payload,
    ): JsonResponse {
        $resolution = $ceremonies->resolve();

        if (! $resolution->isResolved()) {
            return response()->json([
                'status' => 'ceremony_inactive',
                'message' => 'Exactly one active ceremony is required.',
            ], 409);
        }

        return response()->json($payload->forCeremony($resolution->ceremony));
    }

    public function presentNext(
        Request $request,
        PresentStageQueueItem $present,
    ): JsonResponse {
        return $this->run(
            'present_next',
            fn (): array => $present->next($request->user()),
        );
    }

    public function presentSelected(
        Request $request,
        int $queueItem,
        PresentStageQueueItem $present,
    ): JsonResponse {
        return $this->run(
            'present_selected',
            fn (): array => $present->selected(
                $queueItem,
                $request->user(),
            ),
        );
    }

    public function skip(
        Request $request,
        int $queueItem,
        SkipStageQueueItem $skip,
    ): JsonResponse {
        return $this->run(
            'skip',
            fn (): array => $skip->execute($queueItem, $request->user()),
        );
    }

    public function returnToQueue(
        Request $request,
        int $queueItem,
        ReturnStageQueueItem $return,
    ): JsonResponse {
        return $this->run(
            'return_to_queue',
            fn (): array => $return->execute(
                $queueItem,
                $request->user(),
            ),
        );
    }

    public function replay(
        Request $request,
        ReplayCurrentAnnouncement $replay,
    ): JsonResponse {
        return $this->run(
            'replay_announcement',
            fn (): array => $replay->execute($request->user()),
        );
    }

    public function activateHolding(
        Request $request,
        SetStageHolding $holding,
    ): JsonResponse {
        return $this->run(
            'activate_holding',
            fn (): array => $holding->execute(true, $request->user()),
        );
    }

    public function deactivateHolding(
        Request $request,
        SetStageHolding $holding,
    ): JsonResponse {
        return $this->run(
            'deactivate_holding',
            fn (): array => $holding->execute(false, $request->user()),
        );
    }

    public function pauseAutomatic(
        Request $request,
        SetAutomaticPresentationPaused $automatic,
    ): JsonResponse {
        return $this->run(
            'pause_automatic',
            fn (): array => $automatic->execute(true, $request->user()),
        );
    }

    public function resumeAutomatic(
        Request $request,
        SetAutomaticPresentationPaused $automatic,
    ): JsonResponse {
        return $this->run(
            'resume_automatic',
            fn (): array => $automatic->execute(false, $request->user()),
        );
    }

    /**
     * @param  callable(): array{data: array<string, mixed>, http_status: int}  $command
     */
    private function run(string $commandName, callable $command): JsonResponse
    {
        try {
            $result = $command();
        } catch (Throwable $exception) {
            Log::error('Stage-control command failed before confirmation.', [
                'command' => $commandName,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'request_failed',
                'message' => 'The command was not confirmed. Refresh stage control before trying again.',
            ], 500);
        }

        return response()->json($result['data'], $result['http_status']);
    }
}
