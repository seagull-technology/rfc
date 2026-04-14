<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\Entity;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Support\ApplicantDashboardState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $entity = $user->primaryEntity();
        $variant = (string) $request->query('variant', 'default');

        abort_unless($user && $entity, 404);

        $applications = FilmApplication::query()
            ->with(['authorityApprovals', 'submittedBy'])
            ->where('entity_id', $entity->getKey())
            ->latest()
            ->get();
        $scoutingRequests = ScoutingRequest::query()
            ->with('submittedBy')
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
        $previousProjects = $applications
            ->whereIn('status', ['approved', 'rejected'])
            ->sortByDesc(fn (FilmApplication $application) => $application->reviewed_at ?? $application->submitted_at ?? $application->created_at)
            ->values();
        $scoutingRequestsCount = $scoutingRequests->count();
        $approvalAverage = $applications->count() > 0
            ? round(($approvedApplications / $applications->count()) * 100)
            : 0;
        $primaryOwner = $entity->users()
            ->where('users.status', 'active')
            ->orderByDesc('entity_user.is_primary')
            ->orderBy('users.name')
            ->first();
        [$reviewHistory, $reviewerNames] = $this->registrationReviewHistory($entity);
        $activeWorkflowRequests = collect($applications
            ->whereNotIn('status', ['approved', 'rejected'])
            ->map(function (FilmApplication $application): array {
                $state = ApplicantDashboardState::application($application);

                return [
                    'type_label' => __('app.dashboard.production_request_type'),
                    'code' => $application->code,
                    'project_name' => $application->project_name,
                    'summary' => $state['summary'],
                    'status_label' => $application->localizedStatus(),
                    'status_class' => $this->statusClass($application->status),
                    'url' => route('applications.show', $application),
                    'sort_at' => $application->reviewed_at ?? $application->updated_at ?? $application->submitted_at ?? $application->created_at,
                ];
            })
            ->all())
            ->merge($scoutingRequests
                ->whereNotIn('status', ['approved', 'rejected'])
                ->map(function (ScoutingRequest $request): array {
                    $state = ApplicantDashboardState::scouting($request);

                    return [
                        'type_label' => __('app.dashboard.scouting_request_type'),
                        'code' => $request->code,
                        'project_name' => $request->project_name,
                        'summary' => $state['summary'],
                        'status_label' => $request->localizedStatus(),
                        'status_class' => $this->statusClass($request->status),
                        'url' => route('scouting-requests.show', $request),
                        'sort_at' => $request->reviewed_at ?? $request->updated_at ?? $request->submitted_at ?? $request->created_at,
                    ];
                })
                ->all())
            ->sortByDesc(fn (array $item): int => data_get($item, 'sort_at')?->timestamp ?? 0)
            ->values();

        return view($variant === 'foreign_producer' ? 'profile.foreign-producer' : 'profile.show', [
            'user' => $user,
            'entity' => $entity,
            'variant' => $variant,
            'primaryOwner' => $primaryOwner,
            'reviewHistory' => $reviewHistory,
            'reviewerNames' => $reviewerNames,
            'latestRegistrationNotification' => $this->latestRegistrationNotification($user),
            'entityApplications' => $applications,
            'scoutingRequests' => $scoutingRequests,
            'previousProjects' => $previousProjects,
            'activeWorkflowRequests' => $activeWorkflowRequests,
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

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'submitted', 'under_review' => 'warning',
            'needs_clarification', 'rejected' => 'danger',
            'approved' => 'success',
            default => 'secondary',
        };
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function registrationReviewHistory(Entity $entity): array
    {
        $history = collect((array) data_get($entity->metadata, 'review_history', []))
            ->sortByDesc('reviewed_at')
            ->values();

        if ($history->isEmpty()) {
            return [$history, []];
        }

        $reviewerNames = User::query()
            ->withTrashed()
            ->whereIn('id', $history->pluck('reviewed_by_user_id')->filter()->unique()->all())
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->displayName()])
            ->all();

        return [$history, $reviewerNames];
    }

    private function latestRegistrationNotification(User $user): ?DatabaseNotification
    {
        return $user->notifications()
            ->whereIn('data->type_key', ['registration_approved', 'registration_completion_requested'])
            ->latest()
            ->first();
    }
}
