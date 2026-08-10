<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStageDesignTemplateRequest;
use App\Models\StageDesignTemplate;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StageDesignTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-stage-designs');

        return Inertia::render('Admin/StageDesignTemplates/Index', [
            'templates' => StageDesignTemplate::query()
                ->with('updater:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (StageDesignTemplate $template): array => $this->serialize($template)),
        ]);
    }

    public function store(
        SaveStageDesignTemplateRequest $request,
        AdministrativeAuditLogger $auditLogger,
    ): RedirectResponse {
        $template = StageDesignTemplate::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $auditLogger->record($request->user(), 'stage_design_template.created', 'stage_design_template', $template->id, null, $this->snapshot($template), $request->ip());

        return back()->with('success', "Stage design “{$template->name}” saved.");
    }

    public function update(
        SaveStageDesignTemplateRequest $request,
        StageDesignTemplate $stageDesignTemplate,
        AdministrativeAuditLogger $auditLogger,
    ): RedirectResponse {
        $before = $this->snapshot($stageDesignTemplate);
        $stageDesignTemplate->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);
        $auditLogger->record($request->user(), 'stage_design_template.updated', 'stage_design_template', $stageDesignTemplate->id, $before, $this->snapshot($stageDesignTemplate), $request->ip());

        return back()->with('success', "Stage design “{$stageDesignTemplate->name}” updated. Existing ceremonies keep their saved copy.");
    }

    public function destroy(
        Request $request,
        StageDesignTemplate $stageDesignTemplate,
        AdministrativeAuditLogger $auditLogger,
    ): RedirectResponse {
        Gate::authorize('manage-stage-designs');
        $snapshot = $this->snapshot($stageDesignTemplate);
        $id = $stageDesignTemplate->id;
        $name = $stageDesignTemplate->name;
        $stageDesignTemplate->delete();
        $auditLogger->record($request->user(), 'stage_design_template.deleted', 'stage_design_template', $id, $snapshot, null, $request->ip());

        return back()->with('success', "Stage design “{$name}” deleted. Existing ceremonies were not changed.");
    }

    /** @return array<string, mixed> */
    private function serialize(StageDesignTemplate $template): array
    {
        return [
            ...$this->snapshot($template),
            'id' => $template->id,
            'updated_by' => $template->updater?->name,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(StageDesignTemplate $template): array
    {
        return [
            'name' => $template->name,
            'description' => $template->description,
            'theme' => $template->theme,
        ];
    }
}
