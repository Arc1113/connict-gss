<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ManageStudentAwards;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageStudentAwardRequest;
use App\Http\Requests\MoveStudentAwardRequest;
use App\Http\Requests\SaveStudentAwardRequest;
use App\Models\Student;
use App\Models\StudentAward;
use Illuminate\Http\RedirectResponse;

class StudentAwardController extends Controller
{
    public function store(
        SaveStudentAwardRequest $request,
        Student $student,
        ManageStudentAwards $awards,
    ): RedirectResponse {
        $awards->create(
            $student,
            $request->validated(),
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Graduate award added. Preview refreshed.');
    }

    public function update(
        SaveStudentAwardRequest $request,
        Student $student,
        StudentAward $award,
        ManageStudentAwards $awards,
    ): RedirectResponse {
        $awards->update(
            $student,
            $award,
            $request->validated(),
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Graduate award updated. Preview refreshed.');
    }

    public function destroy(
        ManageStudentAwardRequest $request,
        Student $student,
        StudentAward $award,
        ManageStudentAwards $awards,
    ): RedirectResponse {
        $awards->delete($student, $award, $request->user(), $request->ip());

        return back()->with('success', 'Graduate award removed. Preview refreshed.');
    }

    public function move(
        MoveStudentAwardRequest $request,
        Student $student,
        StudentAward $award,
        ManageStudentAwards $awards,
    ): RedirectResponse {
        $awards->move(
            $student,
            $award,
            $request->validated('direction'),
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Graduate award order updated. Preview refreshed.');
    }
}
