<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchScannerStudentsRequest;
use App\Models\Student;
use App\Support\ActiveCeremonyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ScannerLookupController extends Controller
{
    public function __invoke(
        SearchScannerStudentsRequest $request,
        ActiveCeremonyResolver $ceremonies,
    ): JsonResponse {
        $resolution = $ceremonies->resolve();

        if (! $resolution->isResolved()) {
            return response()->json([
                'status' => 'ceremony_inactive',
                'message' => 'Manual lookup is unavailable until exactly one ceremony is active.',
                'students' => [],
            ], 409);
        }

        $term = $request->validated('query');
        $search = '%'.addcslashes($term, '\\%_').'%';
        $students = Student::query()
            ->where('ceremony_id', $resolution->ceremony->id)
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('display_name', 'ilike', $search)
                    ->orWhere('announcement_name', 'ilike', $search)
                    ->orWhere('first_name', 'ilike', $search)
                    ->orWhere('last_name', 'ilike', $search)
                    ->orWhere('student_number', 'ilike', $search);
            })
            ->orderBy('sequence_number')
            ->orderBy('display_name')
            ->limit(10)
            ->get([
                'id',
                'display_name',
                'program',
                'sequence_number',
                'eligibility_status',
            ])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'program' => $student->program,
                'sequence_number' => $student->sequence_number,
                'eligibility' => $student->eligibility_status->label(),
            ]);

        return response()->json([
            'status' => 'ready',
            'message' => $students->isEmpty()
                ? 'No graduate matched this active-ceremony search.'
                : 'Select the verified graduate to process through the scanner workflow.',
            'students' => $students,
        ]);
    }
}
