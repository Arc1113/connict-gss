<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->can('viewAny', Ceremony::class)
                || $request->user()->can('viewAny', Student::class),
            403,
        );

        return redirect()->route('dashboard');
    }
}
