<?php

namespace App\Http\Controllers;

use App\Actions\ScanStudentForPresentation;
use App\Enums\ScanStatus;
use App\Http\Requests\StoreManualScanRequest;
use App\Http\Requests\StoreScanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanController extends Controller
{
    public function store(
        StoreScanRequest $request,
        ScanStudentForPresentation $scan,
    ): JsonResponse {
        try {
            $result = $scan->execute(
                $request->validated('token'),
                $request->user(),
            );
        } catch (Throwable $exception) {
            return $this->failedResponse(
                $request->user()->id,
                'token_scan',
                $exception,
            );
        }

        return response()->json($result->toArray(), $result->status->httpStatus());
    }

    public function manual(
        StoreManualScanRequest $request,
        ScanStudentForPresentation $scan,
    ): JsonResponse {
        try {
            $result = $scan->executeStudent(
                (int) $request->validated('student_id'),
                $request->user(),
            );
        } catch (Throwable $exception) {
            return $this->failedResponse(
                $request->user()->id,
                'verified_student_scan',
                $exception,
            );
        }

        return response()->json($result->toArray(), $result->status->httpStatus());
    }

    private function failedResponse(
        int $operatorId,
        string $command,
        Throwable $exception,
    ): JsonResponse {
        Log::error('Scan command failed before confirmation.', [
            'operator_id' => $operatorId,
            'command' => $command,
            'exception' => $exception::class,
        ]);

        return response()->json([
            'status' => ScanStatus::RequestFailed->value,
            'message' => 'The scan was not confirmed. Check the local server, then try the same card again.',
        ], ScanStatus::RequestFailed->httpStatus());
    }
}
