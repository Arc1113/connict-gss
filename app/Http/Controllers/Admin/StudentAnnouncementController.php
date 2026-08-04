<?php

namespace App\Http\Controllers\Admin;

use App\Actions\GenerateAnnouncementCandidate;
use App\Exceptions\AnnouncementCandidateGenerationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateStudentAnnouncementRequest;
use App\Http\Requests\PreviewStudentAnnouncementRequest;
use App\Models\AnnouncementTemplate;
use App\Models\Student;
use App\Services\PreviewGraduationAnnouncement;
use App\Services\SerializeAnnouncementPreview;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class StudentAnnouncementController extends Controller
{
    public function preview(
        PreviewStudentAnnouncementRequest $request,
        Student $student,
        PreviewGraduationAnnouncement $previewer,
        SerializeAnnouncementPreview $serializer,
    ): JsonResponse {
        $template = $this->resolveTemplate(
            $student,
            $request->validated('announcement_template_id'),
        );
        $preview = $previewer->preview(
            $student,
            $template,
            $request->validated('voice'),
        );

        return response()->json([
            'preview' => $serializer->serialize($preview),
            'selection' => [
                'template_id' => $template?->id,
                'voice' => $preview->composition?->voice,
            ],
        ]);
    }

    public function generate(
        GenerateStudentAnnouncementRequest $request,
        Student $student,
        GenerateAnnouncementCandidate $generator,
        AdministrativeAuditLogger $auditLogger,
    ): RedirectResponse {
        $template = $this->resolveTemplate(
            $student,
            $request->validated('announcement_template_id'),
        );

        try {
            $result = $generator->generate(
                student: $student,
                actor: $request->user(),
                force: $request->boolean('force'),
                template: $template,
                voice: $request->validated('voice'),
            );
        } catch (AnnouncementCandidateGenerationException $exception) {
            throw ValidationException::withMessages([
                'announcement_generation' => $exception->getMessage(),
            ]);
        }

        $auditLogger->record(
            $request->user(),
            $result->reused
                ? 'announcement_generation.reused'
                : 'announcement_generation.requested',
            'announcement_generation',
            $result->generation->id,
            null,
            [
                'student_id' => $student->id,
                'status' => $result->generation->status->value,
                'reused' => $result->reused,
                'character_count' => $result->generation->character_count,
                'voice' => $result->generation->provider_voice,
            ],
            $request->ip(),
        );

        return back()->with(
            'success',
            $result->reused
                ? 'The identical private candidate was reused; Azure was not called.'
                : 'Private announcement candidate generated and ready for review.',
        );
    }

    private function resolveTemplate(Student $student, mixed $templateId): ?AnnouncementTemplate
    {
        if ($templateId === null) {
            return null;
        }

        $template = AnnouncementTemplate::query()
            ->where('ceremony_id', $student->ceremony_id)
            ->find($templateId);

        if ($template === null) {
            throw ValidationException::withMessages([
                'announcement_template_id' => 'Select an announcement template for this graduate’s ceremony.',
            ]);
        }

        return $template;
    }
}
