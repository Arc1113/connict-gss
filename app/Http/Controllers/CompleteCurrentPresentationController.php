<?php

namespace App\Http\Controllers;

use App\Actions\CompleteCurrentPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompleteCurrentPresentationController extends Controller
{
    public function __invoke(
        Request $request,
        CompleteCurrentPresentation $complete,
    ): JsonResponse {
        try {
            $result = $complete->execute($request->user());
        } catch (Throwable $exception) {
            Log::error('Stage completion failed before confirmation.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'request_failed',
                'message' => 'Completion was not confirmed. Refresh stage control before trying again.',
            ], 500);
        }

        return response()->json($result['data'], $result['http_status']);
    }
}
