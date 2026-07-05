<?php

namespace App\Http\Controllers;

use App\Models\Application as FilmApplication;
use App\Models\ApplicationAuthorityApproval;
use App\Models\ScoutingRequest;
use App\Models\User;
use App\Services\AuthorityEscalationService;
use App\Support\ApplicantDashboardState;
use App\Support\ApplicationWorkflowRegistry;
use App\Support\AuthorityApprovalSignal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AuthorityEscalationService $authorityEscalationService,
    ) {
    }

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

        if ($user->registration_type === 'international_producer') {
            return redirect()->route('profile.show', ['variant' => 'foreign_producer']);
        }

        if ($group?->code === 'authorities') {
            $approvalCodes = ApplicationWorkflowRegistry::approvalCodesForEntity($entity);

            $approvals = ApplicationAuthorityApproval::query()
                ->with([
                    'application' => fn ($builder) => $builder
                        ->with(['entity', 'submittedBy', 'officialLetters'])
                        ->withMax([
                            'correspondences as last_external_correspondence_at' => fn (Builder $query): Builder => $query->whereIn('sender_type', ['admin', 'applicant']),
                        ], 'created_at'),
                    'assignedTo',
                    'reviewedBy',
                    'entity',
                ])
                ->where(fn (Builder $query): Builder => $this->restrictApprovalsToAuthorityUser($query, $user->getKey(), $entity, $approvalCodes))
                ->newestFirst()
                ->get();
            $approvalSignals = $approvals
                ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                    $approval->getKey() => AuthorityApprovalSignal::forApproval($approval),
                ]);
            $approvalSlaSignals = $approvals
                ->mapWithKeys(fn (ApplicationAuthorityApproval $approval): array => [
                    $approval->getKey() => $this->authorityEscalationService->signalForApproval($approval, null, $entity),
                ]);
            $approvals = $approvals
                ->sortByDesc(function (ApplicationAuthorityApproval $approval) use ($approvalSignals, $approvalSlaSignals): int {
                    $signal = $approvalSignals->get($approval->getKey(), [
                        'priority' => 0,
                        'at' => null,
                    ]);
                    $slaSignal = $approvalSlaSignals->get($approval->getKey(), [
                        'is_due_soon' => false,
                        'is_overdue' => false,
                        'is_escalated' => false,
                    ]);

                    return (((int) ($slaSignal['is_overdue'] ?? false) * 10) + ((int) ($slaSignal['is_due_soon'] ?? false) * 7) + ((int) ($slaSignal['is_escalated'] ?? false) * 5)) * 100_000_000_000_000_000
                        + ((int) ($signal['priority'] ?? 0) * 10_000_000_000_000_000)
                        + ((int) (($signal['at'] ?? null)?->timestamp ?? $approval->updated_at?->timestamp ?? 0) * 1_000_000)
                        + (int) $approval->getKey();
                })
                ->values();

            return view('dashboard.authority', [
                'user' => $user,
                'entity' => $entity,
                'group' => $group,
                'roles' => $user->roleNamesForEntity($entity),
                'approvals' => $approvals,
                'approvalSignals' => $approvalSignals,
                'approvalSlaSignals' => $approvalSlaSignals,
                'approvalStats' => [
                    'total' => $approvals->count(),
                    'my_assigned' => $approvals->where('assigned_user_id', $user->getKey())->count(),
                    'shared_inbox' => $approvals->whereNull('assigned_user_id')->count(),
                    'pending' => $approvals->where('status', 'pending')->count(),
                    'in_review' => $approvals->where('status', 'in_review')->count(),
                    'resolved' => $approvals->whereIn('status', ['approved', 'rejected'])->count(),
                    'updates' => $approvalSignals->where('key', 'request_update')->count(),
                    'official_books' => $approvalSignals->where('key', 'official_book_issued')->count(),
                    'overdue' => $approvalSlaSignals->where('is_overdue', true)->count(),
                    'escalated' => $approvals->whereNotNull('escalated_at')->count(),
                ],
            ]);
        }

        if ($group?->code === 'rfc') {
            $applications = FilmApplication::query()
                ->with(['entity', 'submittedBy'])
                ->newestFirst()
                ->get();

            $scoutingRequests = ScoutingRequest::query()
                ->with(['entity', 'submittedBy'])
                ->newestFirst()
                ->get();

            return view('dashboard.staff', [
                'user' => $user,
                'entity' => $entity,
                'group' => $group,
                'roles' => $user->roleNamesForEntity($entity),
                'applications' => $applications,
                'scoutingRequests' => $scoutingRequests,
                'applicationStats' => [
                    'total' => $applications->count(),
                    'active_reviews' => $applications->whereIn('status', ['submitted', 'under_review', 'needs_clarification'])->count(),
                    'approved' => $applications->where('status', 'approved')->count(),
                ],
                'scoutingStats' => [
                    'total' => $scoutingRequests->count(),
                    'active_reviews' => $scoutingRequests->whereIn('status', ['submitted', 'under_review'])->count(),
                    'approved' => $scoutingRequests->where('status', 'approved')->count(),
                ],
            ]);
        }

        $view = match ($entity->registration_type) {
            'student' => 'dashboard.applicant',
            'company', 'ngo', 'school' => 'dashboard.organization',
            default => 'dashboard.staff',
        };

        $applications = FilmApplication::query()
            ->with(['submittedBy', 'reviewedBy', 'authorityApprovals.reviewedBy'])
            ->where('entity_id', $entity->getKey())
            ->newestFirst()
            ->get();

        $scoutingRequests = ScoutingRequest::query()
            ->with('submittedBy')
            ->where('entity_id', $entity->getKey())
            ->newestFirst()
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
            ->whereIn('data->type_key', ['registration_approved', 'registration_completion_requested', 'registration_rejected'])
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
            ->sortByDesc(fn (array $item): int => ((int) data_get($item, 'priority', 0) * 10_000_000_000_000_000) + ((data_get($item, 'sort_at')?->timestamp ?? 0) * 1_000_000) + (int) data_get($item, 'sort_id', 0))
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
            ->sortByDesc(fn (array $item): int => ((data_get($item, 'sort_at')?->timestamp ?? 0) * 1_000_000) + (int) data_get($item, 'sort_id', 0))
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
            'sort_id' => $application->getKey(),
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
            'sort_id' => $request->getKey(),
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

    /**
     * @param  array<int, string>  $approvalCodes
     */
    private function restrictApprovalsToAuthorityUser(Builder $query, int $userId, $entity, array $approvalCodes): Builder
    {
        return $query->where(function (Builder $builder) use ($entity, $approvalCodes): void {
            $builder->where('entity_id', $entity->getKey());

            if ($approvalCodes !== []) {
                $builder->orWhere(function (Builder $legacyQuery) use ($approvalCodes): void {
                    $legacyQuery
                        ->whereNull('entity_id')
                        ->whereIn('authority_code', $approvalCodes);
                });
            }
        })->where(function (Builder $builder) use ($userId): void {
            $builder
                ->whereNull('assigned_user_id')
                ->orWhere('assigned_user_id', $userId);
        });
    }
}
