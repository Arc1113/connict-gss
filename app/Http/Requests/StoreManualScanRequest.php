<?php

namespace App\Http\Requests;

use App\Enums\ScanStatus;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreManualScanRequest extends SearchScannerStudentsRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'status' => ScanStatus::Unauthorized->value,
            'message' => 'You are not authorized to operate the scanner.',
        ], ScanStatus::Unauthorized->httpStatus()));
    }
}
