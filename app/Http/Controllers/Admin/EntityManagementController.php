<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\Group;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Notifications\RegistrationApprovedNotification;
use App\Notifications\RegistrationCompletionRequestedNotification;
use App\Services\RoleAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityManagementController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'pending_review', 'needs_completion', 'rejected'])],
            'registration_type' => ['nullable', Rule::in(['all', 'student', 'company', 'ngo', 'school', 'staff'])],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'deleted' => ['nullable', Rule::in(['all', 'without', 'only'])],
        ]);

        $baseQuery = Entity::query()
            ->withTrashed()
            ->with(['group', 'users']);

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $baseQuery->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name_en', 'like', '%'.$search.'%')
                    ->orWhere('name_ar', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('registration_no', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('national_id', 'like', '%'.$search.'%');
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $baseQuery->where('status', $filters['status']);
        }

        if (($filters['registration_type'] ?? 'all') !== 'all') {
            $baseQuery->where('registration_type', $filters['registration_type']);
        }

        if (filled($filters['group_id'] ?? null)) {
            $baseQuery->where('group_id', $filters['group_id']);
        }

        match ($filters['deleted'] ?? 'all') {
            'without' => $baseQuery->whereNull('deleted_at'),
            'only' => $baseQuery->onlyTrashed(),
            default => null,
        };

        return view('admin.entities.index', [
            'entities' => (clone $baseQuery)
                ->withCount('users')
                ->orderByRaw("case when status = 'pending_review' then 0 when status = 'needs_completion' then 1 else 2 end")
                ->orderBy('name_en')
                ->get(),
            'reviewQueue' => Entity::query()
                ->with(['group', 'users'])
                ->whereNull('deleted_at')
                ->whereIn('status', ['pending_review', 'needs_completion', 'rejected'])
                ->orderByRaw("case when status = 'pending_review' then 0 when status = 'needs_completion' then 1 else 2 end")
                ->orderByDesc('updated_at')
                ->get(),
            'groups' => Group::query()
                ->orderBy('id')
                ->get(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'registration_type' => $filters['registration_type'] ?? 'all',
                'group_id' => isset($filters['group_id']) ? (string) $filters['group_id'] : '',
                'deleted' => $filters['deleted'] ?? 'all',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.entities.create', [
            'groups' => Group::query()
                ->with('roles')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'code' => ['nullable', 'string', 'max:255', 'unique:entities,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:255', 'unique:entities,registration_no'],
            'national_id' => ['nullable', 'string', 'max:255', 'unique:entities,national_id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        Entity::query()->create($validated);

        return redirect()
            ->route('admin.entities.index')
            ->with('status', __('app.admin.entities.created'));
    }

    public function show(string $entity): View
    {
        $entity = $this->findEntity($entity);
        $entity->load(['group', 'users']);
        $primaryOwner = $entity->users
            ->sortByDesc(fn (User $user): int => (int) ($user->pivot?->is_primary ?? false))
            ->first();
        $reviewedByUserId = data_get($entity->metadata, 'review.reviewed_by_user_id');
        $reviewHistory = collect((array) data_get($entity->metadata, 'review_history', []))
            ->sortByDesc('reviewed_at')
            ->values();
        $reviewerNames = User::query()
            ->withTrashed()
            ->whereIn('id', $reviewHistory->pluck('reviewed_by_user_id')->filter()->unique()->all())
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->displayName()]);
        $applications = FilmApplication::query()
            ->with(['authorityApprovals', 'submittedBy'])
            ->where('entity_id', $entity->getKey())
            ->latest()
            ->get();
        $monthlyApplications = collect(range(5, 0))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
            ->map(function ($month) use ($applications): array {
                $count = $applications->filter(
                    fn (FilmApplication $application): bool => optional($application->created_at)->format('Y-m') === $month->format('Y-m')
                )->count();

                return [
                    'label' => $month->translatedFormat('M'),
                    'count' => $count,
                ];
            })
            ->values();
        $applicationsByCategory = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->work_category))
            ->groupBy(fn (FilmApplication $application): string => (string) $application->work_category)
            ->map(fn ($group) => $group->count())
            ->sortDesc();
        $budgetByProject = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->estimated_budget))
            ->take(6)
            ->map(fn (FilmApplication $application): array => [
                'label' => $application->project_name,
                'value' => (float) $application->estimated_budget,
            ])
            ->values();
        $crewByProject = $applications
            ->filter(fn (FilmApplication $application): bool => filled($application->estimated_crew_count))
            ->take(6)
            ->map(fn (FilmApplication $application): array => [
                'label' => $application->project_name,
                'value' => (int) $application->estimated_crew_count,
            ])
            ->values();
        $approvalDurationByAuthority = ApplicationAuthorityApproval::query()
            ->whereHas('application', fn (Builder $query): Builder => $query->where('entity_id', $entity->getKey()))
            ->with('application')
            ->whereNotNull('decided_at')
            ->latest('decided_at')
            ->get()
            ->groupBy('authority_code')
            ->map(function ($approvals, string $authorityCode): ?array {
                $durations = $approvals
                    ->map(function (ApplicationAuthorityApproval $approval): ?float {
                        $startedAt = $approval->application?->submitted_at ?? $approval->application?->created_at;

                        if (! $startedAt || ! $approval->decided_at) {
                            return null;
                        }

                        return round($startedAt->diffInHours($approval->decided_at), 1);
                    })
                    ->filter(fn (?float $value): bool => $value !== null);

                if ($durations->isEmpty()) {
                    return null;
                }

                return [
                    'code' => $authorityCode,
                    'average_hours' => round($durations->avg(), 1),
                ];
            })
            ->filter()
            ->sortByDesc('average_hours')
            ->take(6)
            ->values();
        $resolvedApplications = $applications->whereIn('status', ['approved', 'rejected'])->count();
        $approvedApplications = $applications->where('status', 'approved')->count();
        $scoutingRequestsCount = ScoutingRequest::query()
            ->where('entity_id', $entity->getKey())
            ->count();
        $approvalAverage = $applications->count() > 0
            ? round(($approvedApplications / $applications->count()) * 100)
            : 0;

        return view('admin.entities.show', [
            'entity' => $entity,
            'reviewData' => (array) data_get($entity->metadata, 'review', []),
            'reviewHistory' => $reviewHistory,
            'reviewerNames' => $reviewerNames,
            'primaryOwner' => $primaryOwner,
            'reviewedByUser' => $reviewedByUserId ? User::query()->withTrashed()->find($reviewedByUserId) : null,
            'groups' => Group::query()->orderBy('id')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'allowedRoles' => $this->roleAssignmentService->allowedRolesForEntity($entity),
            'members' => $entity->users
                ->sortBy('name')
                ->map(fn (User $user): array => [
                    'user' => $user,
                    'roles' => $user->roleNamesForEntity($entity),
                ]),
            'entityApplications' => $applications,
            'entityAnalytics' => [
                'stats' => [
                    'production_requests' => $applications->count(),
                    'scouting_requests' => $scoutingRequestsCount,
                    'previous_projects' => $resolvedApplications,
                    'approval_average' => $approvalAverage,
                ],
                'charts' => [
                    'applications_by_type' => $applicationsByCategory,
                    'budget_by_project' => $budgetByProject,
                    'applications_by_month' => $monthlyApplications,
                    'crew_by_project' => $crewByProject,
                    'authority_response_average' => $approvalDurationByAuthority,
                ],
            ],
        ]);
    }

    public function update(Request $request, string $entity): RedirectResponse
    {
        $record = $this->findEntity($entity);

        $validated = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('entities', 'code')->ignore($record->getKey())],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:255', Rule::unique('entities', 'registration_no')->ignore($record->getKey())],
            'national_id' => ['nullable', 'string', 'max:255', Rule::unique('entities', 'national_id')->ignore($record->getKey())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'])],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $metadata = $record->metadata ?? [];
        $metadata['address'] = $validated['address'] ?: null;
        $metadata['description'] = $validated['description'] ?: null;

        $record->forceFill([
            'group_id' => $validated['group_id'],
            'code' => $validated['code'] ?: null,
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'],
            'registration_no' => $validated['registration_no'] ?: null,
            'national_id' => $validated['national_id'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'status' => $validated['status'],
            'metadata' => $metadata,
        ])->save();

        return redirect()
            ->route('admin.entities.show', $record->getKey())
            ->with('status', __('app.admin.entities.updated'));
    }

    public function updateStatus(Request $request, string $entity): RedirectResponse
    {
        $record = $this->findEntity($entity);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'])],
        ]);

        DB::transaction(function () use ($record, $validated): void {
            $record->forceFill([
                'status' => $validated['status'],
            ])->save();

            foreach ($record->users as $user) {
                $user->forceFill([
                    'status' => $validated['status'],
                ])->save();
            }
        });

        return back()->with('status', __('app.admin.entities.status_updated'));
    }

    public function downloadRegistrationDocument(string $entity): StreamedResponse|RedirectResponse
    {
        $entity = $this->findEntity($entity);
        $path = data_get($entity->metadata, 'registration_document_path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return redirect()
                ->route('admin.entities.show', $entity)
                ->withErrors([
                    'entity' => __('app.admin.entities.registration_document_missing'),
                ]);
        }

        return Storage::disk('local')->download(
            $path,
            data_get($entity->metadata, 'registration_document_name', basename($path)),
        );
    }

    public function review(Request $request, string $entity): RedirectResponse
    {
        $entity = $this->findEntity($entity);
        $primaryOwner = $entity->users
            ->sortByDesc(fn (User $user): int => (int) ($user->pivot?->is_primary ?? false))
            ->first();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'needs_completion'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match ($validated['decision']) {
            'approve' => 'active',
            'reject' => 'rejected',
            default => 'needs_completion',
        };

        DB::transaction(function () use ($entity, $request, $validated, $status): void {
            $metadata = $entity->metadata ?? [];
            $reviewEntry = array_filter([
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
                'reviewed_at' => now()->toDateTimeString(),
                'reviewed_by_user_id' => $request->user()?->getKey(),
            ], static fn ($value) => $value !== null && $value !== '');
            $history = collect((array) ($metadata['review_history'] ?? []))
                ->push($reviewEntry)
                ->values()
                ->all();

            $metadata['review'] = $reviewEntry;
            $metadata['review_history'] = $history;

            $entity->forceFill([
                'status' => $status,
                'metadata' => $metadata,
            ])->save();

            foreach ($entity->users as $user) {
                $user->forceFill([
                    'status' => $status,
                ])->save();
            }
        });

        if ($primaryOwner) {
            if ($validated['decision'] === 'approve') {
                $primaryOwner->notify(new RegistrationApprovedNotification(
                    entity: $entity->fresh(),
                    note: $validated['note'] ?? null,
                ));
            }

            if (in_array($validated['decision'], ['needs_completion', 'reject'], true)) {
                $primaryOwner->notify(new RegistrationCompletionRequestedNotification(
                    entity: $entity->fresh(),
                    decision: $validated['decision'],
                    note: $validated['note'] ?? null,
                ));
            }
        }

        return redirect()
            ->route('admin.entities.show', $entity)
            ->with('status', __('app.admin.entities.review_saved'));
    }

    public function destroy(string $entity): RedirectResponse
    {
        $record = $this->findEntity($entity);

        if (! $record->trashed()) {
            $record->forceFill(['status' => 'inactive'])->save();
            $record->delete();
        }

        return redirect()
            ->route('admin.entities.index')
            ->with('status', __('app.admin.entities.deleted'));
    }

    public function restore(string $entity): RedirectResponse
    {
        $record = $this->findEntity($entity);

        if ($record->trashed()) {
            $record->restore();
            $record->forceFill([
                'status' => $record->status === 'inactive' ? 'active' : $record->status,
            ])->save();
        }

        return redirect()
            ->route('admin.entities.show', $record->getKey())
            ->with('status', __('app.admin.entities.restored'));
    }

    public function storeMember(Request $request, string $entity): RedirectResponse
    {
        $entity = $this->findEntity($entity);
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'string'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($entity, $user, $validated, $isPrimary): void {
            if ($isPrimary) {
                DB::table('entity_user')
                    ->where('user_id', $user->getKey())
                    ->update(['is_primary' => false]);
            }

            $pivotAttributes = [
                'job_title' => $validated['job_title'] ?? null,
                'is_primary' => $isPrimary,
                'status' => 'active',
            ];

            if ($entity->users()->whereKey($user->getKey())->exists()) {
                $entity->users()->updateExistingPivot($user->getKey(), $pivotAttributes);
            } else {
                $entity->users()->attach($user->getKey(), [
                    ...$pivotAttributes,
                    'joined_at' => now(),
                ]);
            }

            $this->roleAssignmentService->assignToEntity($user, $entity, $validated['role']);
        });

        return redirect()
            ->route('admin.entities.show', $entity)
            ->with('status', __('app.admin.entities.member_added'));
    }

    private function findEntity(int|string $entity): Entity
    {
        return Entity::query()
            ->withTrashed()
            ->with(['group', 'users'])
            ->findOrFail($entity);
    }
}
