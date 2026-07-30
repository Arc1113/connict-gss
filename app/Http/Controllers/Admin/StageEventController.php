<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Models\StageEvent;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\OperationalLogSerializers;
use App\Support\SafeCsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StageEventController extends Controller
{
    public function __construct(
        private readonly OperationalLogSerializers $serializers,
        private readonly SafeCsvExporter $csv,
        private readonly ActiveCeremonyResolver $ceremonies,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view-operational-logs'), 403);

        [$query, $filters] = $this->query($request);

        return Inertia::render('Admin/Logs/StageEvents', [
            'events' => $query
                ->latest('created_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (StageEvent $event): array => $this->serializers->stageEvent($event)),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'canExport' => $request->user()->can('export-operational-reports'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('export-operational-reports'), 403);

        [$query] = $this->query($request);

        $rows = $query
            ->latest('created_at')
            ->latest('id')
            ->limit(5000)
            ->get()
            ->map(fn (StageEvent $event): array => $this->serializers->stageEvent($event));

        return $this->csv->response('gss-stage-events.csv', [
            'time' => 'Timestamp',
            'ceremony' => 'Ceremony',
            'event_type' => 'Event type',
            'graduate' => 'Graduate',
            'actor' => 'Actor',
            'description' => 'Safe description',
            'stage_version' => 'Stage version',
        ], $rows->map(fn (array $row): array => [
            ...$row,
            'event_type' => $row['event_type']['label'],
        ]));
    }

    /**
     * @return array{0: Builder<StageEvent>, 1: array<string, string>}
     */
    private function query(Request $request): array
    {
        $filters = [
            'ceremony_id' => $request->string('ceremony_id')->toString(),
            'event_type' => $request->string('event_type')->toString(),
            'graduate' => trim($request->string('graduate')->toString()),
            'actor_id' => $request->string('actor_id')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
        $user = $request->user();

        $query = StageEvent::query()
            ->with([
                'ceremony:id,name',
                'performedBy:id,name',
                'queueItem.student:id,display_name',
            ]);

        if ($user->hasRole(UserRole::StageOperator)) {
            $resolution = $this->ceremonies->resolve();
            $query->where('ceremony_id', $resolution->ceremony?->id ?? 0);
            $filters['ceremony_id'] = (string) ($resolution->ceremony?->id ?? '');
        }

        $query
            ->when($filters['ceremony_id'] !== '', fn (Builder $query) => $query->where('ceremony_id', $filters['ceremony_id']))
            ->when($filters['event_type'] !== '', fn (Builder $query) => $query->where('event_type', $filters['event_type']))
            ->when($filters['actor_id'] !== '', fn (Builder $query) => $query->where('performed_by', $filters['actor_id']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $filters['to']))
            ->when($filters['graduate'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['graduate'].'%';
                $query->whereHas(
                    'queueItem.student',
                    fn (Builder $query) => $query->where('display_name', 'ilike', $search),
                );
            });

        return [$query, $filters];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'ceremonies' => Ceremony::query()->orderByDesc('event_date')->orderByDesc('id')->get(['id', 'name']),
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => StageEvent::query()
                ->select('event_type')
                ->distinct()
                ->orderBy('event_type')
                ->pluck('event_type')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => str($type)->replace('_', ' ')->title()->toString(),
                ])
                ->values(),
        ];
    }
}
