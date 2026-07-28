<?php

namespace App\Http\Controllers;

use App\Actions\ScanStudentForPresentation;
use App\Http\Requests\StoreScanRequest;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    public function store(
        StoreScanRequest $request,
        ScanStudentForPresentation $scan,
    ): JsonResponse {
        $result = $scan->execute(
            $request->validated('token'),
            $request->user(),
        );

        return response()->json($result->toArray(), $result->status->httpStatus());
    }
}
