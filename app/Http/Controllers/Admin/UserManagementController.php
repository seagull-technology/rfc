<?php

namespace App\Http\Controllers\Admin;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
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
            'deleted' => ['nullable', Rule::in(['all', 'without', 'only'])],
        ]);

        $query = User::query()
            ->withTrashed()
            ->with(['entities.group']);

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('national_id', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhereHas('entities', function (Builder $entityQuery) use ($search): void {
                        $entityQuery
                            ->where('name_en', 'like', '%'.$search.'%')
                            ->orWhere('name_ar', 'like', '%'.$search.'%')
                            ->orWhere('registration_no', 'like', '%'.$search.'%');
                    });
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (($filters['registration_type'] ?? 'all') !== 'all') {
            $query->where('registration_type', $filters['registration_type']);
        }

        match ($filters['deleted'] ?? 'all') {
            'without' => $query->whereNull('deleted_at'),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        return view('admin.users.index', [
            'users' => $query
                ->latest()
                ->get(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'registration_type' => $filters['registration_type'] ?? 'all',
                'deleted' => $filters['deleted'] ?? 'all',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'entities' => Entity::query()
                ->with('group.roles')
                ->whereNull('deleted_at')
                ->orderBy('name_en')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'national_id' => ['required', 'string', 'max:255', 'unique:users,national_id'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'entity_id' => ['required', 'exists:entities,id'],
            'role' => ['required', 'string'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $entity = Entity::query()->with('group.roles')->findOrFail($validated['entity_id']);
        $isPrimary = (bool) ($validated['is_primary'] ?? true);

        DB::transaction(function () use ($validated, $entity, $isPrimary): void {
            $user = User::query()->create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'national_id' => $validated['national_id'],
                'phone' => $validated['phone'],
                'status' => 'active',
                'password' => Hash::make($validated['password']),
            ]);

            $user->entities()->attach($entity->getKey(), [
                'job_title' => $validated['job_title'] ?? null,
                'is_primary' => $isPrimary,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->roleAssignmentService->assignToEntity($user, $entity, $validated['role']);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('app.admin.users.created'));
    }

    public function show(string $user): View
    {
        $user = $this->findUser($user);
        $user->load(['entities.group']);
        $primaryEntity = $user->entities
            ->sortByDesc(fn (Entity $entity): int => (int) ($entity->pivot?->is_primary ?? false))
            ->first();
        $reviewedByUserId = data_get($primaryEntity?->metadata, 'review.reviewed_by_user_id');
        $applications = FilmApplication::query()
            ->with(['entity', 'authorityApprovals'])
            ->where('submitted_by_user_id', $user->getKey())
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
            ->whereHas('application', fn (Builder $query): Builder => $query->where('submitted_by_user_id', $user->getKey()))
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
            ->where('submitted_by_user_id', $user->getKey())
            ->count();
        $approvalAverage = $applications->count() > 0
            ? round(($approvedApplications / $applications->count()) * 100)
            : 0;

        return view('admin.users.show', [
            'user' => $user,
            'primaryEntity' => $primaryEntity,
            'reviewedByUser' => $reviewedByUserId ? User::query()->withTrashed()->find($reviewedByUserId) : null,
            'entities' => Entity::query()
                ->with('group.roles')
                ->whereNull('deleted_at')
                ->orderBy('name_en')
                ->get(),
            'memberships' => $user->entities
                ->sortBy('name_en')
                ->map(fn (Entity $entity): array => [
                    'entity' => $entity,
                    'roles' => $user->roleNamesForEntity($entity),
                ]),
            'userApplications' => $applications,
            'userAnalytics' => [
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

    public function update(Request $request, string $user): RedirectResponse
    {
        $record = $this->findUser($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($record->getKey())],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record->getKey())],
            'national_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'national_id')->ignore($record->getKey())],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($record->getKey())],
            'status' => ['required', Rule::in(['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $record->forceFill([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'national_id' => $validated['national_id'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'status' => $validated['status'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $record->password = Hash::make($validated['password']);
        }

        $record->save();

        return redirect()
            ->route('admin.users.show', $record->getKey())
            ->with('status', __('app.admin.users.updated'));
    }

    public function updateStatus(Request $request, string $user): RedirectResponse
    {
        $record = $this->findUser($user);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'pending_review', 'needs_completion', 'rejected'])],
        ]);

        if ((int) $request->user()?->getKey() === (int) $record->getKey() && $validated['status'] !== 'active') {
            return back()->withErrors([
                'user' => __('app.admin.users.cannot_deactivate_self'),
            ]);
        }

        $record->forceFill([
            'status' => $validated['status'],
        ])->save();

        return back()->with('status', __('app.admin.users.status_updated'));
    }

    public function destroy(Request $request, string $user): RedirectResponse
    {
        $record = $this->findUser($user);

        if ((int) $request->user()?->getKey() === (int) $record->getKey()) {
            return back()->withErrors([
                'user' => __('app.admin.users.cannot_delete_self'),
            ]);
        }

        if (! $record->trashed()) {
            $record->forceFill(['status' => 'inactive'])->save();
            $record->delete();
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('app.admin.users.deleted'));
    }

    public function restore(string $user): RedirectResponse
    {
        $record = $this->findUser($user);

        if ($record->trashed()) {
            $record->restore();
            $record->forceFill([
                'status' => $record->status === 'inactive' ? 'active' : $record->status,
            ])->save();
        }

        return redirect()
            ->route('admin.users.show', $record->getKey())
            ->with('status', __('app.admin.users.restored'));
    }

    public function storeMembership(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);

        $validated = $request->validate([
            'entity_id' => ['required', 'exists:entities,id'],
            'role' => ['required', 'string'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $entity = Entity::query()->with('group.roles')->findOrFail($validated['entity_id']);
        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        DB::transaction(function () use ($user, $entity, $validated, $isPrimary): void {
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

            if ($user->entities()->whereKey($entity->getKey())->exists()) {
                $user->entities()->updateExistingPivot($entity->getKey(), $pivotAttributes);
            } else {
                $user->entities()->attach($entity->getKey(), [
                    ...$pivotAttributes,
                    'joined_at' => now(),
                ]);
            }

            $this->roleAssignmentService->assignToEntity($user, $entity, $validated['role']);
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('app.admin.users.membership_added'));
    }

    private function findUser(int|string $user): User
    {
        return User::query()
            ->withTrashed()
            ->with(['entities.group'])
            ->findOrFail($user);
    }
}
