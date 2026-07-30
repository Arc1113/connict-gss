<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResetCeremonyOperationalState;
use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CeremonyOperationalResetController extends Controller
{
    public function __invoke(
        Request $request,
        Ceremony $ceremony,
        ResetCeremonyOperationalState $reset,
    ): RedirectResponse {
        abort_unless($request->user()->can('reset-ceremony-operations'), 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:RESET CURRENT OPERATIONS'],
        ]);

        $result = $reset->execute($ceremony, $request->user(), $request->ip());

        return redirect()
            ->route('admin.reports.ceremony-summary', [
                'ceremony_id' => $ceremony->id,
            ])
            ->with(
                $result['http_status'] === 200 ? 'success' : 'warning',
                $result['data']['message'],
            );
    }
}
