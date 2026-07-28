<?php

namespace App\Http\Controllers;

use App\Actions\CompleteCurrentPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompleteCurrentPresentationController extends Controller
{
    public function __invoke(
        Request $request,
        CompleteCurrentPresentation $complete,
    ): JsonResponse {
        $result = $complete->execute($request->user());

        return response()->json($result['data'], $result['http_status']);
    }
}
