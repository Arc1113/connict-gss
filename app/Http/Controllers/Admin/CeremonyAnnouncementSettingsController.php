<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VersionAnnouncementTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCeremonyAnnouncementSettingsRequest;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use App\Services\CeremonyAnnouncementSettingsWorkspace;
use App\Services\PreviewFictionalAnnouncement;
use App\Services\SerializeAnnouncementPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CeremonyAnnouncementSettingsController extends Controller
{
    public function show(
        Request $request,
        Ceremony $ceremony,
        CeremonyAnnouncementSettingsWorkspace $workspace,
    ): Response {
        Gate::authorize('view', $ceremony);
        Gate::authorize('manage-announcement-settings');

        return Inertia::render('Admin/Ceremonies/AnnouncementSettings', [
            'announcement' => $workspace->forCeremony($ceremony),
        ]);
    }

    public function store(
        SaveCeremonyAnnouncementSettingsRequest $request,
        Ceremony $ceremony,
        VersionAnnouncementTemplate $versioner,
    ): RedirectResponse {
        $template = $versioner->publish(
            $ceremony,
            $request->validated(),
            $request->user(),
            $request->ip(),
        );

        return back()->with(
            'success',
            "Announcement settings published as version {$template->version}. Existing generated audio was rechecked.",
        );
    }

    public function preview(
        SaveCeremonyAnnouncementSettingsRequest $request,
        Ceremony $ceremony,
        PreviewFictionalAnnouncement $previewer,
        SerializeAnnouncementPreview $serializer,
    ): JsonResponse {
        $template = new AnnouncementTemplate($request->validated());
        $template->ceremony_id = $ceremony->id;
        $template->version = max(
            1,
            (int) AnnouncementTemplate::query()->whereBelongsTo($ceremony)->max('version') + 1,
        );

        return response()->json([
            'preview' => $serializer->serialize($previewer->preview($template)),
        ]);
    }
}
