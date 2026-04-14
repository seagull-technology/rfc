<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Support\ApplicantDashboardState;
use App\Support\ApplicationWorkflowRegistry;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $entity = $user->primaryEntity();
        $group = $entity?->group;

        if ($user->isOperationallyActive() && $entity?->isOperationallyActive() && $user->canAccessAdminPanel($entity)) {
            return redirect()->route('admin.dashboard');
        }

        if (! $entity || ! $user->isOperationallyActive() || ! $entity->isOperationallyActive()) {
            [$reviewHistory, $reviewerNames] = $this->registrationReviewHistory($entity);

            return view('dashboard.registration-status', [
                'user' => $user,
                'entity' => $entity,
                'group' => $group,
                'reviewHistory' => $reviewHistory,
                'reviewerNames' => $reviewerNames,
                'latestRegistrationNotification' => $this->latestRegistrationNotification($user),
            ]);
        }

        if ($group?->code === 'authorities') {
            $approvalCodes = ApplicationWorkflowRegistry::approvalCodesForEntity($entity);

            $approvals = ApplicationAuthorityApproval::query()
                ->with(['application.entity', 'application.submittedBy', 'reviewedBy'])
                ->whereIn('authority_code', $approvalCodes)
                ->latest()
                ->get();

            return view('dashboard.authority', [
                'user' => $user,
                'entity' => $entity,
                'group' => $group,
                'roles' => $user->roleNamesForEntity($entity),
                'approvals' => $approvals,
                'approvalStats' => [
                    'total' => $approvals->count(),
                    'pending' => $approvals->where('status', 'pending')->count(),
                    'in_review' => $approvals->where('status', 'in_review')->count(),
                    'resolved' => $approvals->whereIn('status', ['approved', 'rejected'])->count(),
                ],
            ]);
        }

        $view = match ($entity->registration_type) {
            'student' => 'dashboard.applicant',
            'company', 'ngo', 'school' => 'dashboard.organization',
            default => 'dashboard.index',
        };

        $applications = FilmApplication::query()
            ->with(['submittedBy', 'reviewedBy', 'authorityApprovals.reviewedBy'])
            ->where('entity_id', $entity->getKey())
            ->latest()
            ->get();

        $scoutingRequests = ScoutingRequest::query()
            ->with('submittedBy')
            ->where('entity_id', $entity->getKey())
            ->latest()
            ->get();

        $actionItems = $this->applicantActionItems($applications, $scoutingRequests);
        $latestRequestUpdates = $this->applicantLatestRequestUpdates($applications, $scoutingRequests);

        return view($view, [
            'user' => $user,
            'entity' => $entity,
            'group' => $group,
            'roles' => $user->roleNamesForEntity($entity),
            'applications' => $applications,
            'actionItems' => $actionItems,
            'latestRequestUpdates' => $latestRequestUpdates,
            'applicationStats' => [
                'total' => $applications->count(),
                'drafts' => $applications->where('status', 'draft')->count(),
                'active_reviews' => $applications->whereIn('status', ['submitted', 'under_review', 'needs_clarification'])->count(),
                'approved' => $applications->where('status', 'approved')->count(),
            ],
            'scoutingRequests' => $scoutingRequests,
            'scoutingStats' => [
                'total' => $scoutingRequests->count(),
                'active_reviews' => $scoutingRequests->whereIn('status', ['submitted', 'under_review'])->count(),
                'approved' => $scoutingRequests->where('status', 'approved')->count(),
            ],
        ]);
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function registrationReviewHistory($entity): array
    {
        $history = collect((array) data_get($entity?->metadata, 'review_history', []))
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

    /**
     * @param  Collection<int, FilmApplication>  $applications
     * @param  Collection<int, ScoutingRequest>  $scoutingRequests
     * @return Collection<int, array<string, mixed>>
     */
    private function applicantActionItems(Collection $applications, Collection $scoutingRequests): Collection
    {
        return collect(
            $applications
                ->filter(fn (FilmApplication $application): bool => $application->canBeEditedByApplicant())
                ->map(fn (FilmApplication $application): array => $this->applicationDashboardItem($application))
                ->all()
        )
            ->merge(
                $scoutingRequests
                    ->filter(fn (ScoutingRequest $request): bool => $request->canBeEditedByApplicant())
                    ->map(fn (ScoutingRequest $request): array => $this->scoutingDashboardItem($request))
                    ->all()
            )
            ->sortByDesc(fn (array $item): int => ((int) data_get($item, 'priority', 0) * 1_000_000_000_000) + (data_get($item, 'sort_at')?->timestamp ?? 0))
            ->values();
    }

    /**
     * @param  Collection<int, FilmApplication>  $applications
     * @param  Collection<int, ScoutingRequest>  $scoutingRequests
     * @return Collection<int, array<string, mixed>>
     */
    private function applicantLatestRequestUpdates(Collection $applications, Collection $scoutingRequests): Collection
    {
        return collect($applications->map(fn (FilmApplication $application): array => $this->applicationDashboardItem($application))->all())
            ->merge($scoutingRequests->map(fn (ScoutingRequest $request): array => $this->scoutingDashboardItem($request))->all())
            ->sortByDesc(fn (array $item): int => data_get($item, 'sort_at')?->timestamp ?? 0)
            ->take(5)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationDashboardItem(FilmApplication $application): array
    {
        $sortAt = $application->reviewed_at ?? $application->updated_at ?? $application->submitted_at ?? $application->created_at;
        $state = ApplicantDashboardState::application($application);

        return [
            'type_label' => __('app.dashboard.production_request_type'),
            'code' => $application->code,
            'project_name' => $application->project_name,
            'summary' => $state['summary'],
            'priority' => $state['priority'],
            'status' => $application->status,
            'status_label' => $application->localizedStatus(),
            'status_class' => $this->dashboardStatusClass($application->status),
            'url' => route('applications.show', $application),
            'sort_at' => $sortAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoutingDashboardItem(ScoutingRequest $request): array
    {
        $sortAt = $request->reviewed_at ?? $request->updated_at ?? $request->submitted_at ?? $request->created_at;
        $state = ApplicantDashboardState::scouting($request);

        return [
            'type_label' => __('app.dashboard.scouting_request_type'),
            'code' => $request->code,
            'project_name' => $request->project_name,
            'summary' => $state['summary'],
            'priority' => $state['priority'],
            'status' => $request->status,
            'status_label' => $request->localizedStatus(),
            'status_class' => $this->dashboardStatusClass($request->status),
            'url' => route('scouting-requests.show', $request),
            'sort_at' => $sortAt,
        ];
    }

    private function dashboardStatusClass(?string $status): string
    {
        return match ($status) {
            'submitted', 'under_review' => 'warning',
            'needs_clarification', 'rejected' => 'danger',
            'approved' => 'success',
            default => 'secondary',
        };
    }
}
